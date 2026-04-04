<?php

namespace Database\Seeders;

use App\Models\Gamification\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            // Recovery Badges
            [
                'name' => 'First Step',
                'description' => 'Complete your first habit log or therapy session',
                'icon' => 'badges/first_step.png',
                'category' => 'Recovery',
                'criteria_type' => 'count',
                'criteria_value' => 1,
            ],
            [
                'name' => 'Opening Up',
                'description' => 'Complete 3 therapy sessions',
                'icon' => 'badges/opening_up.png',
                'category' => 'Recovery',
                'criteria_type' => 'session_count',
                'criteria_value' => 3,
            ],
            [
                'name' => 'Warrior',
                'description' => 'Complete 10 therapy sessions',
                'icon' => 'badges/warrior.png',
                'category' => 'Recovery',
                'criteria_type' => 'session_count',
                'criteria_value' => 10,
            ],
            [
                'name' => 'Champion',
                'description' => 'Complete 30 therapy sessions',
                'icon' => 'badges/champion.png',
                'category' => 'Recovery',
                'criteria_type' => 'session_count',
                'criteria_value' => 30,
            ],
            [
                'name' => 'Healed',
                'description' => 'Complete 60+ sessions + clinically meaningful improvement',
                'icon' => 'badges/healed.png',
                'category' => 'Recovery',
                'criteria_type' => 'session_count',
                'criteria_value' => 60,
            ],

            // Community Badges
            [
                'name' => 'Helper',
                'description' => 'Support 5 peers in community',
                'icon' => 'badges/helper.png',
                'category' => 'Community',
                'criteria_type' => 'support_count',
                'criteria_value' => 5,
            ],
            [
                'name' => 'Mentor',
                'description' => 'Support 20 peers in community',
                'icon' => 'badges/mentor.png',
                'category' => 'Community',
                'criteria_type' => 'support_count',
                'criteria_value' => 20,
            ],
            [
                'name' => 'Community Pillar',
                'description' => 'Support 50+ peers in community',
                'icon' => 'badges/community_pillar.png',
                'category' => 'Community',
                'criteria_type' => 'support_count',
                'criteria_value' => 50,
            ],

            // Engagement Badges
            [
                'name' => 'Early Bird',
                'description' => 'Reach a 7-day streak',
                'icon' => 'badges/early_bird.png',
                'category' => 'Engagement',
                'criteria_type' => 'streak',
                'criteria_value' => 7,
            ],
            [
                'name' => 'Consistent',
                'description' => 'Reach a 30-day streak',
                'icon' => 'badges/consistent.png',
                'category' => 'Engagement',
                'criteria_type' => 'streak',
                'criteria_value' => 30,
            ],
            [
                'name' => 'Unstoppable',
                'description' => 'Reach a 100-day streak',
                'icon' => 'badges/unstoppable.png',
                'category' => 'Engagement',
                'criteria_type' => 'streak',
                'criteria_value' => 100,
            ],
            [
                'name' => 'Legendary',
                'description' => 'Reach a 365-day streak',
                'icon' => 'badges/legendary.png',
                'category' => 'Engagement',
                'criteria_type' => 'streak',
                'criteria_value' => 365,
            ],

            // Wellness Badges
            [
                'name' => 'Calm Master',
                'description' => 'Complete 50 meditation sessions',
                'icon' => 'badges/calm_master.png',
                'category' => 'Wellness',
                'criteria_type' => 'meditation_count',
                'criteria_value' => 50,
            ],
            [
                'name' => 'Anxiety Slayer',
                'description' => 'Report 50% anxiety reduction',
                'icon' => 'badges/anxiety_slayer.png',
                'category' => 'Wellness',
                'criteria_type' => 'anxiety_reduction',
                'criteria_value' => 50,
            ],
            [
                'name' => 'Sleep Warrior',
                'description' => 'Improved sleep by 2+ hours',
                'icon' => 'badges/sleep_warrior.png',
                'category' => 'Wellness',
                'criteria_type' => 'sleep_improvement',
                'criteria_value' => 2,
            ],
        ];

        foreach ($badges as $badge) {
            Badge::firstOrCreate(
                ['name' => $badge['name']],
                $badge
            );
        }
    }
}
