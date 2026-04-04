<?php

namespace App\Services\Recommendation;

use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Support\Collection;

class TherapistRecommendationService
{
    public function recommendForUser(User $user, int $limit = 5): Collection
    {
        $goals = collect($user->mental_health_goals ?? []);
        $preferences = collect($user->preferences ?? []);
        $preferredLanguages = collect($preferences->get('languages', []));
        $genderPref = $preferences->get('gender_preference');

        $query = TherapistProfile::query()
            ->with('user')
            ->where('is_verified', true)
            ->where('is_accepting_clients', true);

        $candidates = $query->limit(50)->get();

        $scored = $candidates->map(function (TherapistProfile $tp) use ($goals, $preferredLanguages, $genderPref) {
            $score = 0;

            $specializations = collect($tp->specializations ?? []);
            foreach ($goals as $goal) {
                $score += $specializations->contains(fn ($spec) => str_contains(strtolower((string) $spec), strtolower((string) $goal))) ? 10 : 0;
            }

            $langs = collect($tp->languages ?? []);
            foreach ($preferredLanguages as $lang) {
                $score += $langs->contains(fn ($l) => strtolower((string) $l) === strtolower((string) $lang)) ? 3 : 0;
            }

            if ($genderPref && $tp->user && $tp->user->gender) {
                $score += strtolower((string) $tp->user->gender) === strtolower((string) $genderPref) ? 2 : 0;
            }

            $score += (float) ($tp->rating_average ?? 0);

            $tp->match_score = $score;

            return $tp;
        });

        return $scored->sortByDesc('match_score')->take($limit)->values();
    }
}
