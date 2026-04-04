<?php

namespace App\Services;

use App\Models\Gamification\Badge;
use App\Models\Gamification\Challenge;
use App\Models\Gamification\Streak;
use App\Models\Gamification\UserChallengeProgress;
use App\Models\User;
use App\Notifications\BadgeAwarded;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GamificationService
{
    /**
     * Check and award badges based on criteria
     */
    public function checkAndAwardBadges(User $user, $type, $value)
    {
        $potentialBadges = Badge::where('criteria_type', $type)
            ->where('criteria_value', '<=', $value)
            ->get();

        foreach ($potentialBadges as $badge) {
            if (! $user->badges()->where('badge_id', $badge->id)->exists()) {
                $user->badges()->attach($badge->id, ['awarded_at' => now()]);
                $user->notify(new BadgeAwarded($badge));
            }
        }
    }

    /**
     * Update User Streak (Generic)
     */
    public function updateStreak(User $user)
    {
        $streak = Streak::firstOrCreate(['user_id' => $user->id]);

        $today = Carbon::today();
        $lastActivity = $streak->last_activity_date ? Carbon::parse($streak->last_activity_date) : null;

        if (! $lastActivity) {
            // First activity
            $streak->update([
                'current_streak' => 1,
                'longest_streak' => 1,
                'last_activity_date' => $today,
            ]);
        } elseif ($lastActivity->isYesterday()) {
            // Continued streak
            $streak->increment('current_streak');
            if ($streak->current_streak > $streak->longest_streak) {
                $streak->longest_streak = $streak->current_streak;
            }
            $streak->last_activity_date = $today;
            $streak->save();
        } elseif ($lastActivity->lt($today) && ! $lastActivity->isToday()) {
            // Broken streak
            $streak->update([
                'current_streak' => 1,
                'last_activity_date' => $today,
            ]);
        }

        // Check badges for streaks
        $this->checkAndAwardBadges($user, 'streak_days', $streak->current_streak);

        return $streak;
    }

    /**
     * Check Session Count Badges
     */
    public function checkSessionBadges(User $user)
    {
        // Assuming relationship or count query
        $count = DB::table('therapy_sessions')
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        $this->checkAndAwardBadges($user, 'session_count', $count);
    }

    /**
     * Check Community Support Badges
     */
    public function checkCommunityBadges(User $user)
    {
        // Example: count replies or helpful marks
        // This is a placeholder logic
        $count = 0; // Implement actual counting logic
        $this->checkAndAwardBadges($user, 'peer_support_count', $count);
    }

    /**
     * Process Challenge Progress
     */
    public function updateChallengeProgress(User $user, string $challengeType, int $increment = 1)
    {
        $activeChallenges = Challenge::where('type', $challengeType)
            ->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->get();

        foreach ($activeChallenges as $challenge) {
            $progress = UserChallengeProgress::firstOrCreate(
                ['user_id' => $user->id, 'challenge_id' => $challenge->id],
                ['current_progress' => 0, 'is_completed' => false]
            );

            if (! $progress->is_completed) {
                $progress->increment('current_progress', $increment);

                if ($progress->current_progress >= $challenge->goal_count) {
                    $progress->update([
                        'is_completed' => true,
                        'completed_at' => now(),
                    ]);

                    // Award Reward
                    if ($challenge->reward_type === 'badge') {
                        // Logic to award specific badge if configured
                    }

                    // Notify User
                    // $user->notify(new ChallengeCompleted($challenge));
                }
            }
        }
    }

    // -- Backward Compatibility / Specific Checks --

    public function checkStreakBadges(User $user, $streak)
    {
        // Maps to 'streak_days' in DB
        $this->checkAndAwardBadges($user, 'streak_days', $streak);
    }

    public function checkMilestoneBadges(User $user, $totalCount)
    {
        // Maps to 'habit_count' or generic count
        $this->checkAndAwardBadges($user, 'habit_count', $totalCount);
    }
}
