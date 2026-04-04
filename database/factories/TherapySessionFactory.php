<?php

namespace Database\Factories;

use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TherapySessionFactory extends Factory
{
    protected $model = TherapySession::class;

    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'patient_id' => User::factory(),
            'therapist_id' => User::factory(),
            'session_type' => 'video',
            'status' => 'scheduled',
            'scheduled_at' => now()->addDays(1),
            'duration_minutes' => 60,
            'session_rate' => 100.00,
            'payment_status' => 'pending',
        ];
    }
}
