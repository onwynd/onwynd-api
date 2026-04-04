<?php

namespace App\Repositories\Dashboard;

use App\Models\Dashboard\InstitutionalDashboard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * InstitutionalDashboardRepository
 *
 * Data access layer for B2B institutional metrics
 * Handles partner analytics, ROI calculations, engagement tracking
 */
class InstitutionalDashboardRepository
{
    public function __construct(
        private InstitutionalDashboard $model
    ) {}

    /**
     * Get or create dashboard for institution
     */
    public function getOrCreateForInstitution(int $institutionId): InstitutionalDashboard
    {
        return $this->model->firstOrCreate(
            ['institution_id' => $institutionId]
        );
    }

    /**
     * Update institutional metrics
     */
    public function updateMetrics(int $institutionId): InstitutionalDashboard
    {
        $dashboard = $this->getOrCreateForInstitution($institutionId);

        // Aggregate users from this institution
        $totalUsers = DB::table('users')
            ->where('institution_id', $institutionId)
            ->count();

        $activeUsers = DB::table('users')
            ->join('therapy_sessions', 'users.id', '=', 'therapy_sessions.user_id')
            ->where('users.institution_id', $institutionId)
            ->whereMonth('therapy_sessions.created_at', now()->month)
            ->distinct('users.id')
            ->count('users.id');

        $sessionsCompleted = DB::table('therapy_sessions')
            ->join('users', 'therapy_sessions.user_id', '=', 'users.id')
            ->where('users.institution_id', $institutionId)
            ->where('therapy_sessions.status', 'completed')
            ->count();

        $sessionsThisMonth = DB::table('therapy_sessions')
            ->join('users', 'therapy_sessions.user_id', '=', 'users.id')
            ->where('users.institution_id', $institutionId)
            ->where('therapy_sessions.status', 'completed')
            ->whereMonth('therapy_sessions.completed_at', now()->month)
            ->count();

        $totalInvestment = DB::table('subscriptions')
            ->join('users', 'subscriptions.user_id', '=', 'users.id')
            ->where('users.institution_id', $institutionId)
            ->sum('subscriptions.amount');

        $atRiskUsers = DB::table('users')
            ->join('patient_dashboards', 'users.id', '=', 'patient_dashboards.user_id')
            ->where('users.institution_id', $institutionId)
            ->where('patient_dashboards.current_streak', '=', 0)
            ->orWhere('patient_dashboards.current_mood', '<=', 2)
            ->count();

        $data = [
            'total_users' => $totalUsers,
            'active_users_this_month' => $activeUsers,
            'engagement_rate' => $totalUsers > 0 ? round($activeUsers / $totalUsers, 2) : 0,
            'total_sessions_completed' => $sessionsCompleted,
            'sessions_this_month' => $sessionsThisMonth,
            'total_investment' => $totalInvestment,
            'cost_per_user' => $totalUsers > 0 ? round($totalInvestment / $totalUsers, 2) : 0,
            'at_risk_users' => $atRiskUsers,
            'updated_at' => now(),
        ];

        $dashboard->update($data);
        $this->clearCache($institutionId);

        return $dashboard;
    }

    /**
     * Get dashboard with caching
     */
    public function getWithCache(int $institutionId): ?InstitutionalDashboard
    {
        return Cache::remember(
            "institutional_dashboard_{$institutionId}",
            now()->addHours(12),
            fn () => $this->model->where('institution_id', $institutionId)->first()
        );
    }

    /**
     * Get active institutions
     */
    public function getActive(int $limit = 50): array
    {
        return $this->model->active()
            ->orderByDesc('total_users')
            ->limit($limit)
            ->get()
            ->map(fn ($d) => [
                'institution_id' => $d->institution_id,
                'type' => $d->institution_type,
                'users' => $d->total_users,
                'active_users' => $d->active_users_this_month,
                'engagement_rate' => $d->engagement_rate,
                'contract_ends' => $d->contract_end_date->format('Y-m-d'),
                'status' => $d->getContractStatus(),
            ])
            ->toArray();
    }

    /**
     * Get renewal-due contracts
     */
    public function getRenewalDue(int $limit = 20): array
    {
        return $this->model->renewalDue()
            ->orderBy('contract_end_date')
            ->limit($limit)
            ->get()
            ->map(fn ($d) => [
                'institution_id' => $d->institution_id,
                'name' => $d->institution->name ?? 'Unknown',
                'days_until_renewal' => $d->getDaysUntilRenewal(),
                'total_users' => $d->total_users,
                'engagement_rate' => $d->engagement_rate,
                'retention_risk' => $d->getRetentionRisk(),
            ])
            ->toArray();
    }

    /**
     * Get high-engagement institutions
     */
    public function getHighEngagement(float $minRate = 0.6, int $limit = 20): array
    {
        return $this->model->highEngagement($minRate)
            ->orderByDesc('total_users')
            ->limit($limit)
            ->get()
            ->map(fn ($d) => [
                'institution_id' => $d->institution_id,
                'users' => $d->total_users,
                'engagement' => $d->engagement_rate,
                'sessions_this_month' => $d->sessions_this_month,
                'roi_percentage' => $d->getROIPercentage(),
            ])
            ->toArray();
    }

    /**
     * Get at-risk institutions
     */
    public function getAtRisk(): array
    {
        return $this->model->get()
            ->filter(fn ($d) => $d->shouldPrioritizeSupport())
            ->map(fn ($d) => [
                'institution_id' => $d->institution_id,
                'name' => $d->institution->name ?? 'Unknown',
                'engagement_rate' => $d->engagement_rate,
                'at_risk_users' => $d->at_risk_users,
                'risk_level' => $d->getRetentionRisk(),
                'actions' => [
                    'Schedule check-in call',
                    'Provide support resources',
                    'Offer expansion opportunities',
                ],
            ])
            ->toArray();
    }

    /**
     * Calculate ROI for institution
     */
    public function calculateROI(int $institutionId): array
    {
        $dashboard = $this->getWithCache($institutionId);

        if (! $dashboard) {
            return ['roi' => 0, 'breakdown' => []];
        }

        $absencesSaved = $dashboard->getEstimatedAbsenteeismSavings();
        $productivityGain = $dashboard->total_users * 2000; // Estimated per-user productivity gain
        $totalBenefit = $absencesSaved + $productivityGain;
        $roi = $dashboard->total_investment > 0
            ? (($totalBenefit - $dashboard->total_investment) / $dashboard->total_investment) * 100
            : 0;

        return [
            'roi_percentage' => round($roi, 2),
            'total_investment' => $dashboard->total_investment,
            'estimated_benefit' => $totalBenefit,
            'net_benefit' => $totalBenefit - $dashboard->total_investment,
            'breakdown' => [
                'absences_prevented' => round($absencesSaved, 2),
                'productivity_gain' => round($productivityGain, 2),
            ],
        ];
    }

    /**
     * Get institution summary for reports
     */
    public function getSummaryForReport(int $institutionId): array
    {
        $dashboard = $this->getWithCache($institutionId);

        if (! $dashboard) {
            return [];
        }

        return [
            'institution' => $dashboard->institution->name ?? 'Unknown',
            'type' => $dashboard->institution_type,
            'metrics' => [
                'total_users' => $dashboard->total_users,
                'active_users' => $dashboard->active_users_this_month,
                'engagement_rate' => $dashboard->engagement_rate,
                'sessions_completed' => $dashboard->total_sessions_completed,
                'sessions_this_month' => $dashboard->sessions_this_month,
            ],
            'financial' => [
                'total_investment' => $dashboard->total_investment,
                'cost_per_user' => $dashboard->cost_per_user,
                'roi_percentage' => $dashboard->getROIPercentage(),
            ],
            'health' => [
                'average_wellness_score' => $dashboard->average_wellness_score,
                'at_risk_users' => $dashboard->at_risk_users,
                'top_concerns' => $dashboard->getTopConcernsFormatted(),
            ],
            'contract' => [
                'status' => $dashboard->getContractStatus(),
                'end_date' => $dashboard->contract_end_date->format('Y-m-d'),
                'days_remaining' => $dashboard->getDaysUntilRenewal(),
            ],
        ];
    }

    /**
     * Clear cache
     */
    public function clearCache(int $institutionId): void
    {
        Cache::forget("institutional_dashboard_{$institutionId}");
    }

    /**
     * Bulk update all institutional dashboards
     */
    public function bulkUpdate(): void
    {
        $institutions = DB::table('physical_centers')->pluck('id');

        foreach ($institutions as $institutionId) {
            try {
                $this->updateMetrics($institutionId);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to update institutional dashboard', [
                    'institution_id' => $institutionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
