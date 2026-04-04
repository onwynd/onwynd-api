<?php

namespace App\Repositories\Dashboard;

use App\Models\Dashboard\AdminDashboard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * AdminDashboardRepository
 *
 * Data access layer for platform-wide admin metrics
 * Tracks business health, growth, revenue, and operational KPIs
 */
class AdminDashboardRepository
{
    public function __construct(
        private AdminDashboard $model
    ) {}

    /**
     * Get or create admin dashboard
     */
    public function getOrCreate(): AdminDashboard
    {
        return $this->model->firstOrCreate([]);
    }

    /**
     * Update all platform metrics
     */
    public function updateMetrics(): AdminDashboard
    {
        $dashboard = $this->getOrCreate();

        $totalUsers = DB::table('users')->count();
        $totalTherapists = DB::table('therapists')->count();
        $newUsersToday = DB::table('users')->whereDate('created_at', now())->count();
        $activeUsersToday = DB::table('users')
            ->join('therapy_sessions', 'users.id', '=', 'therapy_sessions.user_id')
            ->whereDate('therapy_sessions.created_at', now())
            ->distinct('users.id')
            ->count('users.id');

        $activeUsersMonth = DB::table('users')
            ->join('therapy_sessions', 'users.id', '=', 'therapy_sessions.user_id')
            ->whereMonth('therapy_sessions.created_at', now()->month)
            ->distinct('users.id')
            ->count('users.id');

        $payingUsers = DB::table('subscriptions')
            ->where('end_date', '>', now())
            ->distinct('user_id')
            ->count('user_id');

        // Revenue calculations
        $d2cRevenueTotal = DB::table('subscriptions')
            ->sum('amount');

        $d2cRevenueMonth = DB::table('subscriptions')
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        $b2bRevenueMonth = DB::table('institutional_dashboards')
            ->whereMonth('updated_at', now()->month)
            ->sum('b2b_revenue_this_month');

        // Marketplace revenue this month = total charged - therapist payouts (session commission)
        // Uses recorded commission_amount to reflect dynamic tiers & founding discount.
        $marketplaceRevenueMonth = DB::table('payments')
            ->join('therapy_sessions', 'payments.session_id', '=', 'therapy_sessions.id')
            ->whereMonth('payments.created_at', now()->month)
            ->sum(DB::raw('payments.amount - COALESCE(therapy_sessions.commission_amount, 0)'));

        $totalSessions = DB::table('therapy_sessions')
            ->where('status', 'completed')
            ->count();

        $sessionsMonth = DB::table('therapy_sessions')
            ->where('status', 'completed')
            ->whereMonth('completed_at', now()->month)
            ->count();

        $avgRating = DB::table('therapy_sessions')
            ->where('status', 'completed')
            ->avg('patient_rating') ?? 0;

        $totalInstitutions = DB::table('physical_centers')->count();
        $activeInstitutions = DB::table('institutional_dashboards')
            ->where('contract_status', 'active')
            ->where('contract_end_date', '>', now())
            ->count();

        $physicalCenters = DB::table('physical_centers')->count();

        $data = [
            'total_users' => $totalUsers,
            'total_therapists' => $totalTherapists,
            'new_users_today' => $newUsersToday,
            'active_users_today' => $activeUsersToday,
            'active_users_this_month' => $activeUsersMonth,
            'paying_users' => $payingUsers,
            'd2c_revenue_total' => $d2cRevenueTotal,
            'd2c_revenue_this_month' => $d2cRevenueMonth,
            'b2b_revenue_this_month' => $b2bRevenueMonth,
            'marketplace_revenue_this_month' => $marketplaceRevenueMonth,
            'total_platform_revenue' => $d2cRevenueTotal + $b2bRevenueMonth + $marketplaceRevenueMonth,
            'total_sessions_completed' => $totalSessions,
            'sessions_this_month' => $sessionsMonth,
            'average_session_rating' => round($avgRating, 2),
            'total_institutions' => $totalInstitutions,
            'active_institutions' => $activeInstitutions,
            'physical_centers' => $physicalCenters,
        ];

        // Calculate platform health
        $data['system_health_score'] = $this->calculateHealthScore($data);
        $data['alerts_critical'] = $this->identifyCriticalAlerts($data);

        $dashboard->update($data);
        $this->clearCache();

        return $dashboard;
    }

    /**
     * Get dashboard with caching
     */
    public function getWithCache(): AdminDashboard
    {
        return Cache::remember(
            'admin_dashboard',
            now()->addHours(1),
            fn () => $this->getOrCreate()
        );
    }

    /**
     * Calculate platform health score (0-100)
     */
    private function calculateHealthScore(array $metrics): float
    {
        $components = [
            'user_growth' => min($metrics['new_users_today'] / 50, 20),
            'engagement' => min($metrics['active_users_today'] / $metrics['total_users'] * 100, 25),
            'revenue' => min($metrics['total_platform_revenue'] / 50000000, 25),
            'therapist_utilization' => min(($metrics['sessions_this_month'] / max($metrics['total_therapists'], 1)) / 20, 15),
            'quality' => ($metrics['average_session_rating'] / 5) * 15,
        ];

        return round(array_sum($components), 2);
    }

    /**
     * Identify critical alerts
     */
    private function identifyCriticalAlerts(array $metrics): array
    {
        $alerts = [];

        if ($metrics['paying_users'] / max($metrics['total_users'], 1) < 0.15) {
            $alerts[] = [
                'type' => 'low_conversion',
                'message' => 'Conversion rate below 15% - review pricing/marketing',
                'severity' => 'high',
            ];
        }

        if ($metrics['active_institutions'] < $metrics['total_institutions'] * 0.7) {
            $alerts[] = [
                'type' => 'inactive_partners',
                'message' => 'Over 30% of institutional partners inactive',
                'severity' => 'high',
            ];
        }

        if ($metrics['average_session_rating'] < 4.0) {
            $alerts[] = [
                'type' => 'quality_issue',
                'message' => 'Average session rating below 4.0 - quality concern',
                'severity' => 'medium',
            ];
        }

        if ($metrics['new_users_today'] < 10) {
            $alerts[] = [
                'type' => 'low_acquisition',
                'message' => 'Daily user acquisition below threshold',
                'severity' => 'medium',
            ];
        }

        return $alerts;
    }

    /**
     * Get growth metrics
     */
    public function getGrowthMetrics(): array
    {
        $dashboard = $this->getWithCache();

        return [
            'user_growth_daily' => $dashboard->new_users_today,
            'user_growth_mau' => $dashboard->active_users_this_month,
            'revenue_growth_mmu' => $dashboard->total_platform_revenue,
            'session_growth' => $dashboard->sessions_this_month,
            'conversion_rate' => $dashboard->getConversionRate().'%',
            'dau_mau_ratio' => $dashboard->getDailyActiveUserRate().'%',
        ];
    }

    /**
     * Get revenue breakdown
     */
    public function getRevenueBreakdown(): array
    {
        $dashboard = $this->getWithCache();

        return [
            'breakdown' => $dashboard->getRevenueBreakdown(),
            'd2c' => $dashboard->d2c_revenue_this_month,
            'b2b' => $dashboard->b2b_revenue_this_month,
            'marketplace' => $dashboard->marketplace_revenue_this_month,
            'total' => $dashboard->total_platform_revenue,
        ];
    }

    /**
     * Get therapist metrics
     */
    public function getTherapistMetrics(): array
    {
        $dashboard = $this->getWithCache();

        return array_merge(
            $dashboard->getTherapistEngagement(),
            [
                'average_rating' => $dashboard->average_session_rating,
                'total_sessions' => $dashboard->total_sessions_completed,
                'monthly_sessions' => $dashboard->sessions_this_month,
            ]
        );
    }

    /**
     * Get institutional metrics
     */
    public function getInstitutionalMetrics(): array
    {
        $dashboard = $this->getWithCache();

        return [
            'total_partners' => $dashboard->total_institutions,
            'active_partners' => $dashboard->active_institutions,
            'activation_rate' => $dashboard->getInstitutionActivationRate().'%',
            'total_institutional_users' => $dashboard->total_customers_from_institutions,
            'b2b_revenue_this_month' => $dashboard->b2b_revenue_this_month,
        ];
    }

    /**
     * Get system status
     */
    public function getSystemStatus(): array
    {
        $dashboard = $this->getWithCache();

        return [
            'health_score' => $dashboard->system_health_score,
            'health_status' => $dashboard->getSystemHealth(),
            'has_alerts' => $dashboard->hasAlerts(),
            'critical_alerts' => $dashboard->getCriticalAlerts(),
            'last_updated' => $dashboard->updated_at->diffForHumans(),
        ];
    }

    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        Cache::forget('admin_dashboard');
    }
}
