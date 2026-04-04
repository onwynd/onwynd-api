<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\AIChat;
use App\Repositories\Contracts\TherapyRepositoryInterface;
use App\Services\OnwyndScoreService;
use App\Services\Therapy\TherapistMatchingService;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    protected $therapyRepository;

    protected $scoreService;

    protected $matchingService;

    public function __construct(
        TherapyRepositoryInterface $therapyRepository,
        OnwyndScoreService $scoreService,
        TherapistMatchingService $matchingService
    ) {
        $this->therapyRepository = $therapyRepository;
        $this->scoreService = $scoreService;
        $this->matchingService = $matchingService;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $upcomingSession = $this->therapyRepository->findUpcomingSessions($user->id, 1)->first();
        $recentSessions = $this->therapyRepository->getSessionHistory($user->id, 5);
        $stats = $this->therapyRepository->getPatientStats($user->id);

        // Calculate Onwynd Score
        $onwyndScore = $this->scoreService->calculateScore($user);

        // Mood Summary Logic
        $moodSummary = [
            'average_mood' => 'N/A',
            'last_check_in' => null,
            'trend' => 'stable',
        ];

        if ($user->patient && $user->patient->moodLogs()->exists()) {
            $logs = $user->patient->moodLogs()
                ->where('created_at', '>=', now()->subDays(30))
                ->orderBy('created_at', 'desc')
                ->get();

            if ($logs->isNotEmpty()) {
                $lastLog = $logs->first();
                $averageScore = $logs->avg('mood_score');

                // Determine trend (compare last 3 vs previous 3)
                $recentAvg = $logs->take(3)->avg('mood_score');
                $olderLogs = $logs->slice(3, 3);
                $previousAvg = $olderLogs->isNotEmpty() ? $olderLogs->avg('mood_score') : $recentAvg;

                $trend = 'stable';
                if ($recentAvg > $previousAvg + 0.5) {
                    $trend = 'improving';
                }
                if ($recentAvg < $previousAvg - 0.5) {
                    $trend = 'declining';
                }

                $moodLabel = match (true) {
                    $averageScore >= 8 => 'Excellent',
                    $averageScore >= 6 => 'Good',
                    $averageScore >= 4 => 'Neutral',
                    $averageScore >= 2 => 'Low',
                    default => 'Very Low'
                };

                $moodSummary = [
                    'average_mood' => $moodLabel,
                    'average_score' => round($averageScore, 1),
                    'last_check_in' => $lastLog->created_at,
                    'trend' => $trend,
                ];
            }
        }

        // Build recent_activities from recent sessions + activity type labels
        $recentActivities = $recentSessions->map(function ($session) {
            return [
                'id' => $session->uuid ?? $session->id,
                'type' => 'session',
                'title' => 'Session with '.optional(optional($session->therapist)->user)->full_name ?? 'Therapist',
                'time' => $session->scheduled_at,
                'icon' => 'session',
            ];
        })->toArray();

        // Recommended Therapists — shape into { user, specializations } for frontend compatibility
        $recommendedTherapists = $this->matchingService->findMatches($user, [], 3)
            ->map(function ($therapistUser) {
                $profile = $therapistUser->therapistProfile;
                $specializations = $therapistUser->specialties
                    ? $therapistUser->specialties->pluck('name')->toArray()
                    : ($profile?->specializations ?? []);

                // Fallback: split display_name if first/last are empty
                $firstName = $therapistUser->first_name
                    ?: ($therapistUser->display_name
                        ? explode(' ', $therapistUser->display_name)[0]
                        : null);
                $lastName = $therapistUser->last_name
                    ?: ($therapistUser->display_name
                        ? (explode(' ', $therapistUser->display_name, 2)[1] ?? null)
                        : null);

                return [
                    'id'              => $profile?->id ?? $therapistUser->id,
                    'uuid'            => $therapistUser->uuid,
                    'user'            => [
                        'id'            => $therapistUser->id,
                        'uuid'          => $therapistUser->uuid,
                        'first_name'    => $firstName,
                        'last_name'     => $lastName,
                        'profile_photo' => $therapistUser->profile_photo_url,
                    ],
                    'specializations' => $specializations,
                    'match_score'     => $therapistUser->match_score ?? 0,
                ];
            })->values();

        // Last AI Conversation Message
        $lastAiMessage = AIChat::where('user_id', $user->id)
            ->where('sender', 'ai')
            ->latest()
            ->first();

        return $this->sendResponse([
            'upcoming_session' => $upcomingSession,
            'recent_sessions' => $recentSessions,
            'recent_activities' => $recentActivities,
            'recommended_therapists' => $recommendedTherapists,
            'ai_conversation' => $lastAiMessage ? [
                'last_message' => $lastAiMessage->message,
                'created_at' => $lastAiMessage->created_at,
            ] : null,
            'stats' => $stats,
            // Flatten stats to top level for frontend compatibility
            'completed_sessions' => $stats['completed_sessions'] ?? 0,
            'upcoming_sessions' => $stats['upcoming_sessions'] ?? 0,
            'mood_streak' => $stats['mood_streak'] ?? 0,
            'unread_messages' => $stats['unread_messages'] ?? 0,
            'active_assessments' => $stats['active_assessments'] ?? 0,
            'mood_summary' => $moodSummary,
            'onwynd_score' => array_merge($onwyndScore, [
                'score' => $onwyndScore['total'] ?? 0,
                'level' => match (true) {
                    ($onwyndScore['total'] ?? 0) >= 80 => 'Excellent',
                    ($onwyndScore['total'] ?? 0) >= 60 => 'Good',
                    ($onwyndScore['total'] ?? 0) >= 40 => 'Fair',
                    default => 'Needs Attention',
                },
            ]),
        ], 'Dashboard data retrieved successfully.');
    }

    public function getScoreDetails(Request $request)
    {
        $user = $request->user();
        $currentScore = $this->scoreService->calculateScore($user);

        // Fetch history if model exists, otherwise empty
        $history = [];
        if (class_exists(\App\Models\OnwyndScoreLog::class)) {
            $history = \App\Models\OnwyndScoreLog::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(30)
                ->get();
        }

        // Factors (mock or real)
        $factors = [
            ['factor' => 'Sleep', 'impact' => 'positive', 'score' => 85],
            ['factor' => 'Mood', 'impact' => 'neutral', 'score' => 60],
            ['factor' => 'Activity', 'impact' => 'negative', 'score' => 40],
        ];

        return $this->sendResponse([
            'current_score' => $currentScore,
            'status' => $currentScore['level'] ?? 'Good',
            'history' => $history,
            'factors_affecting_score' => $factors,
        ], 'Score details retrieved successfully.');
    }

    public function getScoreHistory(Request $request)
    {
        $user = $request->user();
        $history = [];
        if (class_exists(\App\Models\OnwyndScoreLog::class)) {
            $history = \App\Models\OnwyndScoreLog::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        }

        return $this->sendResponse($history, 'Score history retrieved successfully.');
    }

    public function getScoreSuggestions(Request $request)
    {
        $suggestions = [
            ['id' => 1, 'title' => 'Sleep More', 'impact' => 'High', 'description' => 'Try to get 8 hours of sleep.'],
            ['id' => 2, 'title' => 'Meditate', 'impact' => 'Medium', 'description' => '10 minutes of mindfulness.'],
        ];

        return $this->sendResponse([
            'suggestions' => $suggestions,
            'suggestions_count' => count($suggestions),
            'tips_count' => 5,
        ], 'Score suggestions retrieved.');
    }

    public function completeSuggestion(Request $request, $id)
    {
        // Mock completion
        return $this->sendResponse([
            'new_score' => 78,
            'score_increase' => 2,
            'suggestion_id' => $id,
        ], 'Suggestion completed successfully.');
    }

    public function filterScoreSuggestions(Request $request)
    {
        // Mock filtered result
        $suggestions = [
            ['id' => 1, 'title' => 'Sleep More', 'impact' => 'High'],
        ];

        return $this->sendResponse($suggestions, 'Filtered suggestions retrieved.');
    }
}
