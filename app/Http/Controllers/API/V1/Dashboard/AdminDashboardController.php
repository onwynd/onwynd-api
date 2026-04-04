<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\Dashboard\AdminDashboardResource;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * AdminDashboardController
 *
 * Handles platform-wide admin dashboard endpoints
 * GET    /api/v1/dashboard/admin             - Get admin platform dashboard
 * POST   /api/v1/dashboard/admin/refresh     - Refresh all metrics
 * GET    /api/v1/dashboard/admin/alerts      - Get critical alerts
 * GET    /api/v1/dashboard/admin/support     - Get users needing support
 */
class AdminDashboardController extends BaseController
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

    /**
     * Get admin platform dashboard
     */
    public function getDashboard(): JsonResponse
    {
        try {
            // Check admin authorization
            if (! Auth::user()->hasRole('admin')) {
                return $this->error('Unauthorized - Admin access required', 403);
            }

            Log::info('Retrieving admin dashboard');

            $dashboardData = $this->dashboardService->getAdminDashboard();

            return $this->success(
                new AdminDashboardResource($dashboardData),
                'Admin dashboard retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve admin dashboard', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to retrieve dashboard', 500);
        }
    }

    /**
     * Refresh all dashboard metrics
     */
    public function refreshMetrics(): JsonResponse
    {
        try {
            // Check admin authorization
            if (! Auth::user()->hasRole('admin')) {
                return $this->error('Unauthorized - Admin access required', 403);
            }

            Log::info('Admin initiating metrics refresh');

            $result = $this->dashboardService->refreshAllMetrics();

            return $this->success($result, 'Metrics refreshed successfully');
        } catch (\Exception $e) {
            Log::error('Failed to refresh metrics', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to refresh metrics', 500);
        }
    }

    /**
     * Get critical alerts
     */
    public function getCriticalAlerts(): JsonResponse
    {
        try {
            // Check admin authorization
            if (! Auth::user()->hasRole('admin')) {
                return $this->error('Unauthorized - Admin access required', 403);
            }

            Log::info('Retrieving critical alerts');

            $adminDashboard = app()->make(
                \App\Repositories\Dashboard\AdminDashboardRepository::class
            )->getOrCreate();

            $alerts = $adminDashboard->getCriticalAlerts();
            $systemStatus = app()->make(
                \App\Repositories\Dashboard\AdminDashboardRepository::class
            )->getSystemStatus();

            return $this->success(
                [
                    'system_health' => $systemStatus['health_status'],
                    'health_score' => $systemStatus['health_score'],
                    'total_alerts' => count($alerts),
                    'alerts' => $alerts,
                    'requires_attention' => count($alerts) > 0,
                ],
                'Critical alerts retrieved'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve critical alerts', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to retrieve alerts', 500);
        }
    }

    /**
     * Get users needing support
     */
    public function getUsersNeedingSupport(): JsonResponse
    {
        try {
            // Check admin authorization
            if (! Auth::user()->hasRole('admin')) {
                return $this->error('Unauthorized - Admin access required', 403);
            }

            Log::info('Retrieving users needing support');

            $usersAtRisk = $this->dashboardService->getUsersNeedingSupport();

            return $this->success(
                [
                    'total_at_risk' => count($usersAtRisk),
                    'users' => $usersAtRisk,
                ],
                'Users needing support retrieved'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve users needing support', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to retrieve users', 500);
        }
    }

    /**
     * Get institution metrics
     */
    public function getInstitutionMetrics(): JsonResponse
    {
        try {
            // Check admin authorization
            if (! Auth::user()->hasRole('admin')) {
                return $this->error('Unauthorized - Admin access required', 403);
            }

            Log::info('Retrieving institution metrics');

            $repo = app()->make(
                \App\Repositories\Dashboard\InstitutionalDashboardRepository::class
            );

            $activePartners = $repo->getActive();
            $atRiskPartners = $repo->getAtRisk();
            $renewalDue = $repo->getRenewalDue();

            return $this->success(
                [
                    'active_partners' => count($activePartners),
                    'at_risk_partners' => count($atRiskPartners),
                    'renewal_due_count' => count($renewalDue),
                    'top_partners' => array_slice($activePartners, 0, 5),
                    'at_risk_list' => array_slice($atRiskPartners, 0, 5),
                    'renewal_due_list' => array_slice($renewalDue, 0, 5),
                ],
                'Institution metrics retrieved'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve institution metrics', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to retrieve metrics', 500);
        }
    }

    /**
     * Get revenue analytics
     */
    public function getRevenueAnalytics(): JsonResponse
    {
        try {
            // Check admin authorization
            if (! Auth::user()->hasRole('admin')) {
                return $this->error('Unauthorized - Admin access required', 403);
            }

            Log::info('Retrieving revenue analytics');

            $adminRepo = app()->make(
                \App\Repositories\Dashboard\AdminDashboardRepository::class
            );

            $breakdown = $adminRepo->getRevenueBreakdown();

            return $this->success(
                array_merge($breakdown, [
                    'growth_metrics' => $adminRepo->getGrowthMetrics(),
                    'daily_revenue_average' => round($breakdown['total'] / now()->day, 2),
                    'revenue_trends' => $this->getRevenueTrends(),
                ]),
                'Revenue analytics retrieved'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve revenue analytics', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to retrieve analytics', 500);
        }
    }

    /**
     * Get revenue trends
     */
    private function getRevenueTrends(): array
    {
        return [
            'daily' => 'Use daily revenue endpoint',
            'weekly' => 'Aggregate past 7 days',
            'monthly' => 'Aggregate current month',
            'yearly' => 'Aggregate current year',
        ];
    }
}
