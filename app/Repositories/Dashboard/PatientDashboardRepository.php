<?php

namespace App\Repositories\Dashboard;

use App\Models\Dashboard\PatientDashboard;
use App\Models\Subscription;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * PatientDashboardRepository
 *
 * Data access layer for patient dashboard metrics
 * Handles wellness journey tracking, streaks, goals, and engagement metrics
 */
class PatientDashboardRepository
{
    public function __construct(
        private PatientDashboard $model
    ) {}

    /**
     * Get or create dashboard for patient
     */
    public function getOrCreateForPatient(User $user): PatientDashboard
    {
        return $this->model->firstOrCreate(
            ['user_id' => $user->id]
        );
    }

    /**
     * Update patient dashboard metrics
     */
    public function updateMetrics(User $user): PatientDashboard
    {
        $dashboard = $this->getOrCreateForPatient($user);

        $sessionsCompleted = TherapySession::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        $sessionsThisMonth = TherapySession::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->count();

        $pendingSessions = TherapySession::where('user_id', $user->id)
            ->where('status', 'confirmed')
            ->where('scheduled_at', '>', now())
            ->count();

        $subscription = Subscription::where('user_id', $user->id)
            ->where('end_date', '>', now())
            ->latest()
            ->first();

        $moodHistory = DB::table('mood_tracking')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get()
            ->map(fn ($m) => [
                'value' => $m->mood_score,
                'date' => $m->created_at->format('Y-m-d'),
                'note' => $m->note ?? null,
            ])
            ->toArray();

        $data = [
            'sessions_completed' => $sessionsCompleted,
            'sessions_this_month' => $sessionsThisMonth,
            'pending_sessions_booked' => $pendingSessions,
            'subscription_status' => $subscription ? 'premium' : 'free',
            'subscription_expires_at' => $subscription?->end_date,
            'mood_history' => $moodHistory,
        ];

        $dashboard->update($data);
        $this->clearCache($user->id);

        return $dashboard;
    }

    /**
     * Get dashboard with caching
     */
    public function getWithCache(int $userId): ?PatientDashboard
    {
        return Cache::remember(
            "patient_dashboard_{$userId}",
            now()->addHours(2),
            fn () => $this->model->where('user_id', $userId)->first()
        );
    }

    /**
     * Update patient mood
     */
    public function recordMood(int $userId, int $moodScore, ?string $note = null): PatientDashboard
    {
        $dashboard = $this->getOrCreateForPatient(User::findOrFail($userId));

        // Update current mood
        $dashboard->update(['current_mood' => $moodScore]);

        // Log mood history
        DB::table('mood_tracking')->insert([
            'user_id' => $userId,
            'mood_score' => $moodScore,
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->clearCache($userId);

        return $dashboard;
    }

    /**
     * Update streak
     */
    public function updateStreak(int $userId): int
    {
        $dashboard = $this->getOrCreateForPatient(User::findOrFail($userId));

        $today = now()->startOfDay();
        $lastCheckIn = DB::table('mood_tracking')
            ->where('user_id', $userId)
            ->where('created_at', '<', $today)
            ->orderByDesc('created_at')
            ->first();

        if (! $lastCheckIn || now($lastCheckIn->created_at)->diffInDays($today) > 1) {
            // Streak broken
            $dashboard->update(['current_streak' => 1]);
        } else {
            // Extend streak
            $newStreak = $dashboard->current_streak + 1;
            $longestStreak = max($dashboard->longest_streak ?? 0, $newStreak);

            $dashboard->update([
                'current_streak' => $newStreak,
                'longest_streak' => $longestStreak,
            ]);
        }

        $this->clearCache($userId);

        return $dashboard->current_streak;
    }

    /**
     * Update goal progress
     */
    public function updateGoalProgress(int $userId, string $goalId, int $progress): PatientDashboard
    {
        $dashboard = $this->getOrCreateForPatient(User::findOrFail($userId));

        $goalProgress = $dashboard->goal_progress ?? [];
        $goalProgress[$goalId] = min($progress, 100);

        $overallProgress = count($goalProgress) > 0
            ? round(array_sum($goalProgress) / count($goalProgress))
            : 0;

        $dashboard->update([
            'goal_progress' => $goalProgress,
            'overall_progress' => $overallProgress,
        ]);

        $this->clearCache($userId);

        return $dashboard;
    }

    /**
     * Get high-engagement users
     */
    public function getHighEngagement(int $minimumStreak = 7): array
    {
        return $this->model->activeEngagement($minimumStreak)
            ->get()
            ->map(fn ($d) => [
                'user_id' => $d->user_id,
                'name' => $d->user->name,
                'streak' => $d->current_streak,
                'sessions' => $d->sessions_this_month,
                'wellness_score' => $d->getWellnessScore(),
            ])
            ->toArray();
    }

    /**
     * Get users needing support
     */
    public function getUsersNeedingSupport(int $limit = 50): array
    {
        return $this->model->get()
            ->filter(fn ($d) => $d->getNeedsSupportAlert())
            ->take($limit)
            ->map(fn ($d) => [
                'user_id' => $d->user_id,
                'name' => $d->user->name,
                'concern' => $d->primary_concern,
                'mood_trend' => $d->getMoodTrend(),
                'current_mood' => $d->current_mood,
                'actions' => $d->getRecommendedActions(),
            ])
            ->toArray();
    }

    /**
     * Get users by primary concern
     */
    public function getByPrimaryConcern(string $concern): array
    {
        return $this->model->byPrimaryConcern($concern)
            ->get()
            ->map(fn ($d) => [
                'user_id' => $d->user_id,
                'name' => $d->user->name,
                'sessions_completed' => $d->sessions_completed,
                'wellness_score' => $d->getWellnessScore(),
                'trend' => $d->getMoodTrend(),
            ])
            ->toArray();
    }

    /**
     * Clear cache
     */
    public function clearCache(int $userId): void
    {
        Cache::forget("patient_dashboard_{$userId}");
    }

    /**
     * Get cohort statistics
     */
    public function getCohortStats(): array
    {
        $dashboards = $this->model->get();

        return [
            'total_users' => $dashboards->count(),
            'average_wellness_score' => round($dashboards->avg(fn ($d) => $d->getWellnessScore()), 2),
            'users_with_active_streaks' => $dashboards->filter(fn ($d) => $d->isStreakActive())->count(),
            'average_sessions_per_user' => round($dashboards->avg('sessions_completed'), 2),
            'users_needing_support' => $dashboards->filter(fn ($d) => $d->getNeedsSupportAlert())->count(),
            'top_concerns' => $dashboards->groupBy('primary_concern')
                ->map(fn ($g) => $g->count())
                ->sortDesc()
                ->take(5)
                ->toArray(),
        ];
    }
}
