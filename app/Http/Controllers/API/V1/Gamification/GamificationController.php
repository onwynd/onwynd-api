<?php

namespace App\Http\Controllers\API\V1\Gamification;

use App\Http\Controllers\API\BaseController;
use App\Models\Gamification\Badge;
use App\Models\Gamification\Challenge;
use App\Models\Gamification\Leaderboard;
use App\Models\Gamification\Streak;
use App\Models\Gamification\UserChallengeProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GamificationController extends BaseController
{
    /**
     * Get user's full gamification profile
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $data = [
            'badges' => $this->getBadgesData($user),
            'streak' => $this->getStreakData($user),
            'challenges' => $this->getChallengesData($user),
            'next_badge' => $this->getNextBadge($user),
        ];

        // M.2: Personal Ranking Gate
        if ($user->hasActiveSubscription() || $user->has_unlimited_quota) {
            $data['rank'] = $this->getUserRank($user);
        }

        return $this->sendResponse($data, 'Gamification profile retrieved successfully.');
    }

    /**
     * Get user's streak details
     */
    public function streak(Request $request)
    {
        return $this->sendResponse(
            $this->getStreakData($request->user()),
            'Streak details retrieved successfully.'
        );
    }

    /**
     * Get user's badges
     */
    public function badges(Request $request)
    {
        return $this->sendResponse(
            $this->getBadgesData($request->user()),
            'Badges retrieved successfully.'
        );
    }

    /**
     * Get current weekly challenge
     */
    public function currentChallenge(Request $request)
    {
        $challenges = $this->getChallengesData($request->user());
        // Prefer the first active one
        $current = $challenges->first();

        if (! $current) {
            return $this->sendResponse(null, 'No active challenges at the moment.');
        }

        return $this->sendResponse($current, 'Current challenge retrieved successfully.');
    }

    /**
     * Get leaderboards
     */
    public function leaderboards(Request $request)
    {
        $user = $request->user();

        // M.1: Global Leaderboard Gate
        if (! $user->hasActiveSubscription() && ! $user->has_unlimited_quota) {
            return $this->sendError('Leaderboard is coming soon for non-subscribers.', ['status' => 'coming_soon'], 403);
        }

        $type = $request->query('type', 'streak'); // default to streak leaderboard
        $limit = $request->query('limit', 10);
        $week = $request->query('week', now()->format('o-W')); // ISO Year-Week

        // If leaderboard table is populated via job, query it.
        // For real-time fallback or if table empty, we might calculate on fly.
        // Here we assume the table is populated.

        $leaders = Leaderboard::with('user:id,name,avatar') // assuming avatar exists or will be added
            ->where('week', $week)
            ->where('type', $type)
            ->orderBy('rank', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($entry) {
                return [
                    'rank' => $entry->rank,
                    'user_name' => $entry->user->name,
                    // 'avatar' => $entry->user->avatar, // specific to user model
                    'score' => $entry->score,
                ];
            });

        // If empty, maybe generate on the fly for demo purposes?
        if ($leaders->isEmpty() && $type === 'streak') {
            // Fallback: Get top streaks from Streaks table
            $leaders = Streak::with('user:id,name')
                ->orderBy('current_streak', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($streak, $index) {
                    return [
                        'rank' => $index + 1,
                        'user_name' => $streak->user->name,
                        'score' => $streak->current_streak,
                    ];
                });
        }

        return $this->sendResponse($leaders, 'Leaderboard retrieved successfully.');
    }

    /**
     * Record a daily check-in and update the user's streak.
     */
    public function checkIn(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        $streak = Streak::firstOrCreate(
            ['user_id' => $user->id],
            ['current_streak' => 0, 'longest_streak' => 0, 'last_activity_date' => null]
        );

        $lastActivity = $streak->last_activity_date?->toDateString();

        if ($lastActivity === $today) {
            return $this->sendResponse(
                $this->getStreakData($user),
                'Already checked in today.'
            );
        }

        $yesterday = now()->subDay()->toDateString();

        if ($lastActivity === $yesterday) {
            $streak->current_streak += 1;
        } else {
            // Streak broken — reset
            $streak->current_streak = 1;
        }

        if ($streak->current_streak > $streak->longest_streak) {
            $streak->longest_streak = $streak->current_streak;
        }

        $streak->last_activity_date = $today;
        $streak->save();

        return $this->sendResponse(
            $this->getStreakData($user),
            'Check-in recorded successfully.'
        );
    }

    /**
     * Mark a badge as showcased on the user's profile.
     * The user_badges table has no showcased column — we return the badge data
     * as confirmation. A dedicated column can be added in a future migration.
     */
    public function showcaseBadge(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'badge_id' => 'required|integer|exists:badges,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $user = $request->user();
        $badge = $user->badges()->where('badges.id', $request->badge_id)->first();

        if (! $badge) {
            return $this->sendError('Badge not found in your collection.', [], 404);
        }

        return $this->sendResponse([
            'id' => $badge->id,
            'name' => $badge->name,
            'icon_url' => $badge->icon,
            'showcased' => true,
        ], 'Badge showcased successfully.');
    }

    /**
     * Claim a challenge reward once the challenge is completed.
     */
    public function claimReward(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_id' => 'required|integer|exists:challenges,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $user = $request->user();
        $challenge = Challenge::find($request->challenge_id);

        $progress = UserChallengeProgress::where('user_id', $user->id)
            ->where('challenge_id', $challenge->id)
            ->first();

        if (! $progress) {
            return $this->sendError('No progress found for this challenge.', [], 404);
        }

        if (! $progress->is_completed) {
            return $this->sendError('Challenge not yet completed. Keep going!', [], 422);
        }

        return $this->sendResponse([
            'challenge_id' => $challenge->id,
            'reward_type' => $challenge->reward_type,
            'reward_value' => $challenge->reward_value,
            'claimed_at' => now()->toISOString(),
        ], 'Reward claimed successfully.');
    }

    // --- Helpers ---

    private function getBadgesData($user)
    {
        return $user->badges()
            ->orderBy('pivot_awarded_at', 'desc')
            ->get()
            ->map(function ($badge) {
                return [
                    'id' => $badge->id,
                    'name' => $badge->name,
                    'description' => $badge->description,
                    'icon_url' => $badge->icon,
                    'category' => $badge->category,
                    'awarded_at' => $badge->pivot->awarded_at,
                ];
            });
    }

    private function getStreakData($user)
    {
        $streak = Streak::where('user_id', $user->id)->first();

        return [
            'current_streak' => $streak ? $streak->current_streak : 0,
            'longest_streak' => $streak ? $streak->longest_streak : 0,
            'last_activity' => $streak ? $streak->last_activity_date : null,
        ];
    }

    private function getChallengesData($user)
    {
        return Challenge::where('is_active', true)
            ->where('end_date', '>=', now())
            ->get()
            ->map(function ($challenge) use ($user) {
                $progress = $user->challengeProgress()
                    ->where('challenge_id', $challenge->id)
                    ->first();

                return [
                    'id' => $challenge->id,
                    'title' => $challenge->title,
                    'description' => $challenge->description,
                    'goal' => $challenge->goal_count,
                    'current_progress' => $progress ? $progress->current_progress : 0,
                    'is_completed' => $progress ? $progress->is_completed : false,
                    'days_remaining' => now()->diffInDays($challenge->end_date),
                    'reward_type' => $challenge->reward_type,
                    'reward_value' => $challenge->reward_value,
                ];
            });
    }

    private function getNextBadge($user)
    {
        return Badge::whereDoesntHave('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->orderBy('criteria_value', 'asc')->first();
    }

    private function getUserRank($user, $type = 'streak')
    {
        $week = now()->format('o-W');
        $entry = Leaderboard::where('user_id', $user->id)
            ->where('week', $week)
            ->where('type', $type)
            ->first();

        if ($entry) {
            return $entry->rank;
        }

        // Fallback: calculate rank manually from streaks if leaderboard job hasn't run
        if ($type === 'streak') {
            $userStreak = Streak::where('user_id', $user->id)->first();
            if (! $userStreak) {
                return null;
            }

            $rank = Streak::where('current_streak', '>', $userStreak->current_streak)->count() + 1;

            return $rank;
        }

        return null;
    }
}
