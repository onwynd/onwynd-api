<?php

namespace App\Services\Therapy;

use App\Models\Therapist;
use App\Models\Therapy\MatchingPreference;
use App\Models\User;
use App\Models\UserAssessmentResult;
use App\Services\PlatformSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TherapistMatchingService
{
    protected $calculator;

    public function __construct(MatchingScoreCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * Update matching preferences based on assessment result
     */
    public function updatePreferencesFromAssessment(User $user, UserAssessmentResult $result): void
    {
        $assessment = $result->assessment;
        $title = strtolower($assessment->title ?? '');

        $newSpecialties = [];
        if (strpos($title, 'phq-9') !== false || strpos($title, 'depression') !== false) {
            $newSpecialties[] = 'Depression';
        }
        if (strpos($title, 'gad-7') !== false || strpos($title, 'anxiety') !== false) {
            $newSpecialties[] = 'Anxiety';
        }
        if (strpos($title, 'pss-10') !== false || strpos($title, 'stress') !== false) {
            $newSpecialties[] = 'Stress Management';
        }

        if (empty($newSpecialties)) {
            return;
        }

        $preferences = $this->resolvePreferences($user, []);
        $existingSpecialties = $preferences->specialties ?? [];

        // Merge and unique
        $merged = array_unique(array_merge($existingSpecialties, $newSpecialties));

        $this->savePreferences($user, ['specialties' => $merged]);
    }

    /**
     * Find best matches for a user based on preferences
     *
     * @param  User  $user  The patient
     * @param  array  $criteria  Override or new criteria
     * @param  int  $limit  Number of results
     */
    public function findMatches(User $user, array $criteria = [], int $limit = 5): Collection
    {
        // 1. Get or Create Preferences
        $preferences = $this->resolvePreferences($user, $criteria);

        // 2. Initial Filtering (Database Level)
        $query = User::query()
            ->whereHas('role', function ($q) {
                $q->where('slug', 'therapist');
            })
            ->whereHas('therapistProfile', function ($q) {
                $q->where('is_verified', true)
                    ->where('is_accepting_clients', true);
            })
            ->where(function ($q) {
                $q->whereNotNull('first_name')->where('first_name', '!=', '')
                  ->orWhereNotNull('display_name')->where('display_name', '!=', '');
            })
            ->with(['therapistProfile', 'specialties', 'availability']); // Eager load for scoring + display

        $candidates = $query->limit(50)->get(); // Get a pool of candidates to score

        // 3. Scoring (Application Level)
        $scoredCandidates = $candidates->map(function ($therapist) use ($preferences) {
            $score = $this->calculator->calculateScore($therapist, $preferences);
            $therapist->match_score = $score;

            return $therapist;
        });

        // 4. Sort and Limit
        return $scoredCandidates
            ->sortByDesc('match_score')
            ->take($limit)
            ->values();
    }

    /**
     * Save user preferences for future
     */
    public function savePreferences(User $user, array $data): MatchingPreference
    {
        return MatchingPreference::updateOrCreate(
            ['user_id' => $user->id],
            $data
        );
    }

    /**
     * 3-state regional matching entry-point.
     * Returns a JsonResponse with therapists + metadata for the API controller.
     *
     * States:
     *  'on'          — hard regional filter (Nigerian IP → available_for_nigeria,
     *                  international IP → available_for_international)
     *  'conditional' — regional first; expands to language-only if no match
     *  'off'         — all therapists returned (language filter always applied)
     */
    public function getMatchedTherapistsForRequest(Request $request, User $user): JsonResponse
    {
        $state = PlatformSettingsService::get('regional_matching_state', 'on');
        $userLanguage = $user->preferred_language ?? $user->language ?? 'English';
        $userRegion = $this->detectRegion($request->ip());

        $baseQuery = Therapist::with([
            'user:id,name,email',
            'schedule' => function ($q) {
                $q->where('is_available', true)
                  ->where(function ($inner) {
                      $inner->whereNull('specific_date')  // recurring slots always included
                            ->orWhereBetween('specific_date', [now()->toDateString(), now()->addDays(7)->toDateString()]);
                  });
            },
        ])
            ->where('is_verified', true)
            ->where('is_accepting_clients', true)
            ->whereJsonContains('languages', $userLanguage);

        $expanded = false;
        $matchType = 'regional';

        if ($state === 'off') {
            $therapists = $baseQuery->get();
            $matchType = 'all';

        } elseif ($state === 'on') {
            $field = $userRegion === 'NG' ? 'available_for_nigeria' : 'available_for_international';
            $therapists = (clone $baseQuery)->where($field, true)->get();

            if ($therapists->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No therapists are currently available in your language in your region. We are growing our team. Please check back soon.',
                    'state' => 'no_regional_match',
                    'expanded' => false,
                    'total' => 0,
                ]);
            }

        } else { // conditional
            $field = $userRegion === 'NG' ? 'available_for_nigeria' : 'available_for_international';
            $therapists = (clone $baseQuery)->where($field, true)->get();

            if ($therapists->isEmpty()) {
                $therapists = $baseQuery->get();
                $expanded = true;
                $matchType = 'language';
            }

            if ($therapists->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No therapists are currently available in your language. We are growing our team. Please check back soon.',
                    'state' => 'no_language_match',
                    'expanded' => false,
                    'total' => 0,
                ]);
            }
        }

        // Ranking
        $therapists = $therapists->sortByDesc(function (Therapist $t) use ($user) {
            $score = ($t->rating_average ?? 0) * 20;
            $score += ($t->total_sessions ?? 0) * 0.1;
            if ($user->primary_concern ?? null) {
                $specs = is_array($t->specializations) ? $t->specializations : ($t->specializations ?? []);
                if (in_array($user->primary_concern, $specs)) {
                    $score += 30;
                }
            }
            $competencies = is_array($t->cultural_competencies) ? $t->cultural_competencies : [];
            if (in_array('Nigerian diaspora experience', $competencies)) {
                $score += 15;
            }
            return $score;
        })->values();

        $therapists->each(function (Therapist $t) use ($matchType, $expanded) {
            $t->match_type = $matchType;
            $t->is_expanded_result = $expanded;
        });

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\TherapistProfileCardResource::collection($therapists),
            'expanded' => $expanded,
            'expansion_message' => $expanded
                ? 'No therapists were available in your region. Showing therapists who speak your language from our wider network.'
                : null,
            'total' => $therapists->count(),
        ]);
    }

    /**
     * Detect if an IP belongs to Nigeria or is international.
     * Returns 'NG' for Nigerian IPs, 'INTL' for all others.
     */
    private function detectRegion(string $ip): string
    {
        try {
            // Use cached geo lookup if GeoService is available
            if (app()->bound(\App\Services\GeoService::class)) {
                $geo = app(\App\Services\GeoService::class)->lookup($ip);
                return ($geo['country_code'] ?? 'INTL') === 'NG' ? 'NG' : 'INTL';
            }
        } catch (\Throwable $e) {
            Log::warning('TherapistMatchingService: geo lookup failed', ['error' => $e->getMessage()]);
        }

        return 'INTL'; // Safe default: international view
    }

    /**
     * Helper to merge saved prefs with runtime criteria
     */
    protected function resolvePreferences(User $user, array $criteria): MatchingPreference
    {
        if (! empty($criteria)) {
            // Transient preference object for calculation
            $pref = new MatchingPreference($criteria);
            $pref->user_id = $user->id;

            return $pref;
        }

        return $user->matchingPreference ?? new MatchingPreference;
    }
}
