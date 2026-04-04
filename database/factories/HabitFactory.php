<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Habit>
 */
class HabitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'frequency' => 'daily',
            'start_date' => now(),
            'target_count' => 1,
            'streak' => 0,
            'longest_streak' => 0,
            'is_archived' => false,
            'reminder_times' => ['09:00'],
        ];
    }
}
