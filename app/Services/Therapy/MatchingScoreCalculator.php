<?php

namespace App\Services\Therapy;

use App\Models\Therapy\MatchingPreference;
use App\Models\User;

class MatchingScoreCalculator
{
    // Weight configurations (can be moved to config)
    const WEIGHT_SPECIALTY = 0.30;

    const WEIGHT_AVAILABILITY = 0.20;

    const WEIGHT_LANGUAGE = 0.15;

    const WEIGHT_GENDER = 0.10;

    const WEIGHT_STYLE = 0.10;

    const WEIGHT_RATING = 0.10;

    const WEIGHT_WORKLOAD = 0.05;

    /**
     * Calculate score for a single therapist against preferences
     */
    public function calculateScore(User $therapist, MatchingPreference $preferences): float
    {
        $score = 0;
        $profile = $therapist->therapistProfile;

        if (! $profile) {
            return 0;
        }

        // 1. Specialty Match (30%)
        $therapistSpecialties = array_map('strtolower', $profile->specializations ?? []);
        $preferredSpecialties = array_map('strtolower', $preferences->specialties ?? []);

        if (! empty($preferredSpecialties)) {
            $matches = array_intersect($preferredSpecialties, $therapistSpecialties);
            $specialtyScore = count($matches) / count($preferredSpecialties);
            $score += $specialtyScore * self::WEIGHT_SPECIALTY;
        } else {
            // If no preference, neutral score
            $score += 0.5 * self::WEIGHT_SPECIALTY;
        }

        // 2. Language Match (15%)
        $therapistLangs = array_map('strtolower', $profile->languages ?? ['en']);
        $preferredLangs = array_map('strtolower', $preferences->languages ?? ['en']);

        $langMatches = array_intersect($preferredLangs, $therapistLangs);
        if (count($langMatches) > 0) {
            $score += 1.0 * self::WEIGHT_LANGUAGE;
        }

        // 3. Gender Preference (10%)
        if ($preferences->gender_preference && $preferences->gender_preference !== 'no_preference') {
            if (strtolower($therapist->gender) === strtolower($preferences->gender_preference)) {
                $score += 1.0 * self::WEIGHT_GENDER;
            }
        } else {
            $score += 1.0 * self::WEIGHT_GENDER; // Full points if no preference
        }

        // 4. Communication Style (10%)
        // TherapistProfile doesn't have communication_style yet, let's skip or use a default
        // In real world, we'd add this field.
        $score += 0.5 * self::WEIGHT_STYLE;

        // 5. Ratings & Success (10%)
        // Normalize rating (0-5) to 0-1
        $ratingScore = ($profile->rating_average ?? 0) / 5;
        $score += $ratingScore * self::WEIGHT_RATING;

        // 6. Workload Balance (5%)
        // Profile doesn't have max_workload yet, let's assume 1.0
        $score += 1.0 * self::WEIGHT_WORKLOAD;

        // 7. Availability (20%)
        $score += 0.8 * self::WEIGHT_AVAILABILITY;

        return round($score * 100, 1); // Return 0-100 score
    }
}
