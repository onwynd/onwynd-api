<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\Dashboard\GetInstitutionalDashboardRequest;
use App\Http\Resources\Dashboard\InstitutionalDashboardResource;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * InstitutionalDashboardController
 *
 * Handles B2B institutional dashboard endpoints
 * GET    /api/v1/dashboard/institution/{institutionId}         - Get institution dashboard
 * GET    /api/v1/dashboard/institution/{institutionId}/roi      - Get ROI metrics
 * GET    /api/v1/dashboard/institution/{institutionId}/health   - Get health assessment
 * GET    /api/v1/dashboard/institution/active                   - List active partners
 * GET    /api/v1/dashboard/institution/renewal-due              - Get contracts needing renewal
 */
class InstitutionalDashboardController extends BaseController
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

    /**
     * Get institutional dashboard
     */
    public function getDashboard(GetInstitutionalDashboardRequest $request, int $institutionId): JsonResponse
    {
        try {
            Log::info('Retrieving institutional dashboard', ['institution_id' => $institutionId]);

            $dashboardData = $this->dashboardService->getInstitutionalDashboard($institutionId);

            return $this->success(
                new InstitutionalDashboardResource($dashboardData),
                'Institutional dashboard retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve institutional dashboard', [
                'institution_id' => $institutionId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to retrieve dashboard', 500);
        }
    }

    /**
     * Get ROI analysis for institution
     */
    public function getROI(int $institutionId): JsonResponse
    {
        try {
            Log::info('Retrieving institution ROI', ['institution_id' => $institutionId]);

            $repo = app()->make(
                \App\Repositories\Dashboard\InstitutionalDashboardRepository::class
            );

            $roi = $repo->calculateROI($institutionId);

            return $this->success(
                array_merge($roi, [
                    'interpretation' => $this->interpretROI($roi['roi_percentage']),
                    'benchmarks' => $this->getBenchmarks(),
                ]),
                'ROI analysis retrieved'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve ROI', [
                'institution_id' => $institutionId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to retrieve ROI data', 500);
        }
    }

    /**
     * Get health assessment
     */
    public function getHealthAssessment(int $institutionId): JsonResponse
    {
        try {
            Log::info('Retrieving institution health assessment', ['institution_id' => $institutionId]);

            $repo = app()->make(
                \App\Repositories\Dashboard\InstitutionalDashboardRepository::class
            );

            $dashboard = $repo->getWithCache($institutionId);

            if (! $dashboard) {
                return $this->error('Institution not found', 404);
            }

            return $this->success(
                [
                    'engagement_health' => $this->getEngagementHealth($dashboard),
                    'wellness_health' => $this->getWellnessHealth($dashboard),
                    'contract_health' => $this->getContractHealth($dashboard),
                    'overall_health_status' => $dashboard->getRetentionRisk(),
                    'action_items' => $this->generateActionItems($dashboard),
                ],
                'Health assessment retrieved'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve health assessment', [
                'institution_id' => $institutionId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to retrieve health assessment', 500);
        }
    }

    /**
     * Get active institutional partners
     */
    public function getActivePartners(): JsonResponse
    {
        try {
            Log::info('Retrieving active institutional partners');

            $repo = app()->make(
                \App\Repositories\Dashboard\InstitutionalDashboardRepository::class
            );

            $partners = $repo->getActive();

            return $this->success(
                [
                    'total_active' => count($partners),
                    'partners' => $partners,
                ],
                'Active partners retrieved'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve active partners', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to retrieve partners', 500);
        }
    }

    /**
     * Get contracts needing renewal
     */
    public function getRenewalDue(): JsonResponse
    {
        try {
            Log::info('Retrieving renewal-due contracts');

            $repo = app()->make(
                \App\Repositories\Dashboard\InstitutionalDashboardRepository::class
            );

            $renewals = $repo->getRenewalDue();

            return $this->success(
                [
                    'total_needing_renewal' => count($renewals),
                    'contracts' => $renewals,
                ],
                'Renewal-due contracts retrieved'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve renewal-due contracts', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to retrieve contracts', 500);
        }
    }

    /**
     * Get engagement health metrics
     */
    private function getEngagementHealth($dashboard): array
    {
        $score = $dashboard->engagement_rate * 100;

        return [
            'score' => round($score, 2),
            'status' => match (true) {
                $score >= 70 => 'Excellent',
                $score >= 50 => 'Good',
                $score >= 30 => 'Fair',
                default => 'Poor'
            },
            'active_users' => $dashboard->active_users_this_month,
            'total_users' => $dashboard->total_users,
        ];
    }

    /**
     * Get wellness health metrics
     */
    private function getWellnessHealth($dashboard): array
    {
        $riskPercentage = $dashboard->total_users > 0
            ? ($dashboard->at_risk_users / $dashboard->total_users) * 100
            : 0;

        return [
            'at_risk_percentage' => round($riskPercentage, 2),
            'at_risk_count' => $dashboard->at_risk_users,
            'average_wellness_score' => $dashboard->average_wellness_score,
            'status' => match (true) {
                $riskPercentage <= 5 => 'Excellent',
                $riskPercentage <= 15 => 'Good',
                $riskPercentage <= 25 => 'Fair',
                default => 'Poor'
            },
        ];
    }

    /**
     * Get contract health metrics
     */
    private function getContractHealth($dashboard): array
    {
        return [
            'status' => $dashboard->getContractStatus(),
            'days_remaining' => $dashboard->getDaysUntilRenewal(),
            'contract_end_date' => $dashboard->contract_end_date?->format('Y-m-d'),
            'is_active' => $dashboard->isContractActive(),
        ];
    }

    /**
     * Generate action items for institution
     */
    private function generateActionItems($dashboard): array
    {
        $actions = [];

        if ($dashboard->engagement_rate < 0.5) {
            $actions[] = [
                'priority' => 'high',
                'action' => 'Increase user engagement through better onboarding',
                'suggested_approach' => 'Schedule training session for HR team',
            ];
        }

        if ($dashboard->at_risk_users > $dashboard->total_users * 0.1) {
            $actions[] = [
                'priority' => 'high',
                'action' => 'Support at-risk users with targeted interventions',
                'suggested_approach' => 'Offer additional mental health resources',
            ];
        }

        if ($dashboard->getDaysUntilRenewal() <= 30) {
            $actions[] = [
                'priority' => 'urgent',
                'action' => 'Initiate contract renewal discussions',
                'suggested_approach' => 'Schedule executive review meeting',
            ];
        }

        return $actions;
    }

    /**
     * Interpret ROI percentage
     */
    private function interpretROI(float $roi): string
    {
        return match (true) {
            $roi >= 300 => 'Exceptional ROI - Highly strategic investment',
            $roi >= 200 => 'Excellent ROI - Strong business case',
            $roi >= 100 => 'Good ROI - Worthwhile investment',
            $roi >= 0 => 'Positive ROI - Value-generating program',
            default => 'Negative ROI - Program needs optimization'
        };
    }

    /**
     * Get industry benchmarks
     */
    private function getBenchmarks(): array
    {
        return [
            'typical_roi_range' => '150-250%',
            'typical_engagement_rate' => '60-75%',
            'typical_roi_payback_period' => '18-24 months',
            'typical_estimated_absenteeism_reduction' => '15-25%',
        ];
    }
}
