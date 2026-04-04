<?php

namespace App\Repositories\Dashboard;

use App\Models\Dashboard\TherapistDashboard;
use App\Models\Therapist;
use App\Models\TherapySession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * TherapistDashboardRepository
 *
 * Data access layer for therapist dashboard metrics
 * Handles aggregations, caching, and business logic
 */
class TherapistDashboardRepository
{
    public function __construct(
        private TherapistDashboard $model
    ) {}

    /**
     * Get or create dashboard for therapist
     */
    public function getOrCreateForTherapist(Therapist $therapist): TherapistDashboard
    {
        return $this->model->firstOrCreate(
            ['therapist_id' => $therapist->id],
            ['user_id' => $therapist->user_id]
        );
    }

    /**
     * Update therapist dashboard metrics
     */
    public function updateMetrics(Therapist $therapist): TherapistDashboard
    {
        $dashboard = $this->getOrCreateForTherapist($therapist);

        $sessionsCompleted = TherapySession::where('therapist_id', $therapist->id)
            ->where('status', 'completed')
            ->count();

        $sessionsThisMonth = TherapySession::where('therapist_id', $therapist->id)
            ->where('status', 'completed')
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->count();

        $pendingSessions = TherapySession::where('therapist_id', $therapist->id)
            ->where('status', 'confirmed')
            ->where('scheduled_at', '>', now())
            ->count();

        // FIX-1: Use session_rate (therapist's rate only) rather than amount (which
        // includes the platform booking fee). COALESCE falls back to amount for
        // historical rows created before the session_rate column was added.
        // Sum recorded commission_amount to reflect dynamic tiers & founding discount
        $totalEarnings = DB::table('payments')
            ->join('therapy_sessions', 'payments.session_id', '=', 'therapy_sessions.id')
            ->where('therapy_sessions.therapist_id', $therapist->id)
            ->where('payments.status', 'completed')
            ->sum(DB::raw('COALESCE(therapy_sessions.commission_amount, 0)'));

        $earningsThisMonth = DB::table('payments')
            ->join('therapy_sessions', 'payments.session_id', '=', 'therapy_sessions.id')
            ->where('therapy_sessions.therapist_id', $therapist->id)
            ->where('payments.status', 'completed')
            ->whereMonth('payments.created_at', now()->month)
            ->whereYear('payments.created_at', now()->year)
            ->sum(DB::raw('COALESCE(therapy_sessions.commission_amount, 0)'));

        $averageRating = DB::table('therapy_sessions')
            ->where('therapist_id', $therapist->id)
            ->where('status', 'completed')
            ->avg('patient_rating') ?? 0;

        $totalRatings = TherapySession::where('therapist_id', $therapist->id)
            ->where('status', 'completed')
            ->whereNotNull('patient_rating')
            ->count();

        $uniquePatients = DB::table('therapy_sessions')
            ->where('therapist_id', $therapist->id)
            ->distinct('user_id')
            ->count('user_id');

        $data = [
            'total_patients' => $uniquePatients,
            'sessions_completed_total' => $sessionsCompleted,
            'sessions_this_month' => $sessionsThisMonth,
            'pending_sessions' => $pendingSessions,
            'total_earnings_lifetime' => $totalEarnings,
            'total_earnings_this_month' => $earningsThisMonth,
            'average_rating' => round($averageRating, 2),
            'total_ratings' => $totalRatings,
            'last_activity_at' => now(),
        ];

        $dashboard->update($data);
        $this->clearCache($therapist->id);

        return $dashboard;
    }

    /**
     * Get dashboard with caching
     */
    public function getWithCache(int $therapistId): ?TherapistDashboard
    {
        return Cache::remember(
            "therapist_dashboard_{$therapistId}",
            now()->addHours(6),
            fn () => $this->model->where('therapist_id', $therapistId)->first()
        );
    }

    /**
     * Get top rated therapists
     */
    public function getTopRated(int $limit = 10): array
    {
        // FIX-4: eager-load user relationship to avoid N+1 (one query instead of N)
        return $this->model->topRated($limit)
            ->with('user')
            ->get()
            ->map(fn ($d) => [
                'therapist_id' => $d->therapist_id,
                'name' => $d->user->name,
                'rating' => $d->average_rating,
                'total_sessions' => $d->sessions_completed_total,
                'patients' => $d->total_patients,
            ])
            ->toArray();
    }

    /**
     * Get highest earners
     */
    public function getHighestEarners(int $limit = 10): array
    {
        // FIX-4: eager-load user relationship to avoid N+1
        return $this->model->highestEarners($limit)
            ->with('user')
            ->get()
            ->map(fn ($d) => [
                'therapist_id' => $d->therapist_id,
                'name' => $d->user->name,
                'earnings_this_month' => $d->total_earnings_this_month,
                'lifetime_earnings' => $d->total_earnings_lifetime,
                'sessions' => $d->sessions_this_month,
            ])
            ->toArray();
    }

    /**
     * Get therapists by specialization
     */
    public function getBySpecialization(string $specialization): array
    {
        // FIX-4: push filter to DB (eliminates full-table scan) and eager-load user
        // (eliminates N+1 lazy loads). Requires specializations to be a JSON column.
        return $this->model
            ->whereJsonContains('specializations', $specialization)
            ->with('user')
            ->get()
            ->map(fn ($d) => [
                'therapist_id' => $d->therapist_id,
                'name' => $d->user->name,
                'specializations' => $d->specializations,
                'rating' => $d->average_rating,
                'available_slots' => $d->pending_sessions < 20,
            ])
            ->toArray();
    }

    /**
     * Clear cache
     */
    public function clearCache(int $therapistId): void
    {
        Cache::forget("therapist_dashboard_{$therapistId}");
    }

    /**
     * Bulk update multiple dashboards
     */
    public function bulkUpdate(array $therapistIds): void
    {
        foreach ($therapistIds as $therapistId) {
            $therapist = Therapist::find($therapistId);
            if ($therapist) {
                $this->updateMetrics($therapist);
            }
        }
    }
}
