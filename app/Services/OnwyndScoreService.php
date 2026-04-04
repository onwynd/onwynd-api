<?php

namespace App\Services;

use App\Models\MindfulnessActivity;
use App\Models\MoodLog;
use App\Models\OnwyndScoreLog;
use App\Models\SleepLog;
use App\Models\User;
use Carbon\Carbon;

class OnwyndScoreService
{
    public function updateScore(User $user)
    {
        $scoreData = $this->calculateScore($user);

        // Log the score
        OnwyndScoreLog::create([
            'user_id' => $user->id,
            'score' => $scoreData['total'],
            'breakdown' => $scoreData['breakdown'],
            'logged_at' => now(),
        ]);

        // Update User Profile Cache
        $user->profile()->update([
            'onwynd_score_cache' => $scoreData['total'],
        ]);

        return $scoreData;
    }

    public function calculateScore(User $user)
    {
        $sleepScore = $this->calculateSleepScore($user);
        $moodScore = $this->calculateMoodScore($user);
        $mindfulnessScore = $this->calculateMindfulnessScore($user);
        $activityScore = $this->calculateActivityScore($user); // Placeholder/Basic

        // Weighted Average
        // Sleep: 30%, Mood: 30%, Mindfulness: 20%, Activity: 20%
        $total = ($sleepScore * 0.3) + ($moodScore * 0.3) + ($mindfulnessScore * 0.2) + ($activityScore * 0.2);

        return [
            'total' => round($total),
            'breakdown' => [
                'sleep' => $sleepScore,
                'mood' => $moodScore,
                'mindfulness' => $mindfulnessScore,
                'activity' => $activityScore,
            ],
        ];
    }

    private function calculateSleepScore(User $user)
    {
        // Get last sleep log
        $lastLog = SleepLog::where('user_id', $user->id)
            ->where('start_time', '>=', Carbon::now()->subHours(24))
            ->orderBy('end_time', 'desc')
            ->first();

        if (! $lastLog) {
            return 50;
        } // Default baseline

        // Duration Score (Ideal 7-9 hours = 420-540 mins)
        $duration = $lastLog->duration_minutes;
        $durationScore = 0;
        if ($duration >= 420 && $duration <= 540) {
            $durationScore = 100;
        } elseif ($duration < 420) {
            // Penalize for less sleep
            $durationScore = 100 - ((420 - $duration) / 420 * 100);
        } else {
            // Penalize for too much sleep (less severe)
            $durationScore = 100 - (($duration - 540) / 540 * 50);
        }

        // Quality Score
        $qualityScore = $lastLog->quality_rating ?? 70; // Default 70 if not rated

        return ($durationScore * 0.6) + ($qualityScore * 0.4);
    }

    private function calculateMoodScore(User $user)
    {
        // Average mood over last 3 days
        $avgMood = MoodLog::where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::now()->subDays(3))
            ->avg('mood_score');

        if (! $avgMood) {
            return 50;
        }

        // Normalize to 0-100. Assuming 1-10 scale:
        return $avgMood * 10;
    }

    private function calculateMindfulnessScore(User $user)
    {
        // Minutes in last 24h
        $minutes = MindfulnessActivity::where('user_id', $user->id)
            ->where('completed_at', '>=', Carbon::now()->subHours(24))
            ->sum('duration_seconds') / 60;

        // Target: 20 mins/day = 100 points
        if ($minutes >= 20) {
            return 100;
        }

        return ($minutes / 20) * 100;
    }

    private function calculateActivityScore(User $user)
    {
        // This would ideally come from integration (Google Fit, Apple Health)
        // For now, we return a baseline or look at UserActivity table if populated
        return 50; // Neutral score
    }
}
