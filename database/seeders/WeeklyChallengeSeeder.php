<?php

namespace Database\Seeders;

use App\Models\Gamification\Challenge;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WeeklyChallengeSeeder extends Seeder
{
    public function run(): void
    {
        $startDate = Carbon::now()->startOfWeek();

        $challenges = [
            [
                'title' => 'Anxiety-Free Week',
                'description' => 'Report 0 anxiety episodes for 3+ days this week.',
                'type' => 'anxiety_free',
                'goal_count' => 3,
                'reward_type' => 'badge',
                'reward_value' => 'Anxiety Slayer',
                'start_date' => $startDate->copy(),
                'end_date' => $startDate->copy()->endOfWeek(),
                'is_active' => true,
            ],
            [
                'title' => 'Community Love Week',
                'description' => 'Support 5 peers in the community forum.',
                'type' => 'community_support',
                'goal_count' => 5,
                'reward_type' => 'credit',
                'reward_value' => '2000', // 2000 NGN
                'start_date' => $startDate->copy()->addWeek(),
                'end_date' => $startDate->copy()->addWeek()->endOfWeek(),
                'is_active' => false,
            ],
            [
                'title' => 'Meditation Marathon',
                'description' => 'Complete 20 min meditation daily for 7 days.',
                'type' => 'meditation',
                'goal_count' => 7,
                'reward_type' => 'credit',
                'reward_value' => '5000', // 5000 NGN
                'start_date' => $startDate->copy()->addWeeks(2),
                'end_date' => $startDate->copy()->addWeeks(2)->endOfWeek(),
                'is_active' => false,
            ],
            [
                'title' => 'Therapist Connection Week',
                'description' => 'Book 1 therapist session this week.',
                'type' => 'booking',
                'goal_count' => 1,
                'reward_type' => 'session',
                'reward_value' => 'free_session',
                'start_date' => $startDate->copy()->addWeeks(3),
                'end_date' => $startDate->copy()->addWeeks(3)->endOfWeek(),
                'is_active' => false,
            ],
        ];

        foreach ($challenges as $challenge) {
            Challenge::updateOrCreate(
                ['title' => $challenge['title']],
                $challenge
            );
        }
    }
}
