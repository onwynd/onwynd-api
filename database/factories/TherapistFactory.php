<?php

namespace Database\Factories;

use App\Models\Therapist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TherapistFactory extends Factory
{
    protected $model = Therapist::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => 'active',
            'specializations' => ['Anxiety', 'Depression'],
            'qualifications' => ['PhD', 'Masters'],
            'languages' => ['English', 'Spanish'],
            'experience_years' => $this->faker->numberBetween(1, 20),
            'hourly_rate' => $this->faker->randomFloat(2, 50, 200),
            'bio' => $this->faker->paragraph,
            'license_number' => $this->faker->unique()->bothify('LIC-#####'),
            'license_state' => $this->faker->state,
            'license_expiry' => $this->faker->dateTimeBetween('+1 year', '+5 years'),
            'currency' => 'USD',
            'is_verified' => true,
            'verified_at' => now(),
            'is_accepting_clients' => true,
        ];
    }
}
