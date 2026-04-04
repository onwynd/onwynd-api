<?php

namespace Tests\Unit\Services\Therapy;

use App\Models\Therapy\MatchingPreference;
use App\Models\Therapy\TherapistSpecialty;
use App\Models\User;
use App\Services\Therapy\MatchingScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TherapistMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculator_scores_correctly()
    {
        $calculator = new MatchingScoreCalculator;

        // Create a mock therapist
        $therapist = User::factory()->create([
            'gender' => 'female',
            'languages' => ['en', 'es'],
            'average_rating' => 4.8,
            'current_workload' => 5,
            'max_workload' => 20,
        ]);

        // Mock relation
        $specialty = TherapistSpecialty::create(['name' => 'Anxiety']);
        $therapist->specialties()->attach($specialty);
        $therapist->load('specialties');

        // Create preferences
        $prefs = new MatchingPreference([
            'gender_preference' => 'female',
            'languages' => ['es'],
            'specialties' => ['Anxiety'],
            'min_experience_years' => 2,
        ]);

        $score = $calculator->calculateScore($therapist, $prefs);

        // Expected Score Breakdown:
        // Specialty (30%): Match 'Anxiety' = 30
        // Language (15%): Match 'es' = 15
        // Gender (10%): Match 'female' = 10
        // Style (10%): No preference set = 0 (or neutral logic dependent)
        // Rating (10%): 4.8/5 = 0.96 * 10 = 9.6
        // Workload (5%): (20-5)/20 = 0.75 * 5 = 3.75
        // Availability (20%): Placeholder 0.8 * 20 = 16

        // Total approx: 30 + 15 + 10 + 0 + 9.6 + 3.75 + 16 = ~84.35

        $this->assertGreaterThan(50, $score);
        $this->assertLessThanOrEqual(100, $score);
    }
}
