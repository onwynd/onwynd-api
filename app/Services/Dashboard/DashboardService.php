<?php

namespace App\Services\Dashboard;

use App\Models\Therapist;
use App\Models\User;
use App\Repositories\Dashboard\AdminDashboardRepository;
use App\Repositories\Dashboard\InstitutionalDashboardRepository;
use App\Repositories\Dashboard\PatientDashboardRepository;
use App\Repositories\Dashboard\TherapistDashboardRepository;
use Illuminate\Support\Facades\Log;

/**
 * DashboardService
 *
 * Orchestrates dashboard data retrieval and business logic
 * Provides unified interface for all dashboard types
 */
class DashboardService
{
    public function __construct(
        private TherapistDashboardRepository $therapistRepo,
        private PatientDashboardRepository $patientRepo,
        private InstitutionalDashboardRepository $institutionalRepo,
        private AdminDashboardRepository $adminRepo
    ) {}

    /**
     * Get patient dashboard data
     */
    public function getPatientDashboard(User $user): array
    {
        try {
            $dashboard = $this->patientRepo->getWithCache($user->id);

            if (! $dashboard) {
                Log::warning('Patient dashboard not found', ['user_id' => $user->id]);

                return [];
            }

            return [
                'user_id' => $user->id,
                'wellness' => [
                    'wellness_score' => $dashboard->getWellnessScore(),
                    'mood_trend' => $dashboard->getMoodTrend(),
                    'current_mood' => $dashboard->current_mood,
                    'needs_support' => $dashboard->getNeedsSupportAlert(),
                    'recommended_actions' => $dashboard->getRecommendedActions(),
                ],
                'engagement' => [
                    'current_streak' => $dashboard->current_streak,
                    'longest_streak' => $dashboard->longest_streak,
                    'streak_active' => $dashboard->isStreakActive(),
                    'ai_check_ins' => $dashboard->ai_check_ins,
                    'community_posts' => $dashboard->community_participations,
                ],
                'therapy' => [
                    'sessions_completed' => $dashboard->sessions_completed,
                    'sessions_this_month' => $dashboard->sessions_this_month,
                    'pending_sessions' => $dashboard->pending_sessions_booked,
                    'next_session' => $dashboard->next_session_at?->format('Y-m-d H:i'),
                ],
                'goals' => [
                    'active_goals' => $dashboard->active_goals ?? [],
                    'progress' => $dashboard->goal_progress ?? [],
                    'overall_progress' => $dashboard->overall_progress,
                    'completed_count' => $dashboard->getGoalCompletionCount(),
                ],
                'subscription' => [
                    'status' => $dashboard->subscription_status,
                    'is_active' => $dashboard->hasActiveSubscription(),
                    'days_remaining' => $dashboard->getSubscriptionDaysRemaining(),
                    'expires_at' => $dashboard->subscription_expires_at?->format('Y-m-d'),
                ],
                'wellness_history' => array_slice($dashboard->mood_history ?? [], 0, 7),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve patient dashboard', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get therapist dashboard data
     */
    public function getTherapistDashboard(Therapist $therapist): array
    {
        try {
            $dashboard = $this->therapistRepo->getWithCache($therapist->id);

            if (! $dashboard) {
                Log::warning('Therapist dashboard not found', ['therapist_id' => $therapist->id]);

                return [];
            }

            return [
                'therapist_id' => $therapist->id,
                'performance' => [
                    'rank' => $dashboard->getPerformanceRank(),
                    'is_top_performer' => $dashboard->isTopPerformer(),
                    'average_rating' => $dashboard->average_rating,
                    'total_ratings' => $dashboard->total_ratings,
                    'response_time_hours' => $dashboard->response_time_hours,
                    'patient_satisfaction' => $dashboard->patient_satisfaction_percentage,
                ],
                'patients' => [
                    'total_patients' => $dashboard->total_patients,
                    'sessions_completed' => $dashboard->sessions_completed_total,
                    'sessions_this_month' => $dashboard->sessions_this_month,
                    'pending_sessions' => $dashboard->pending_sessions,
                ],
                'earnings' => [
                    'lifetime_earnings' => $dashboard->total_earnings_lifetime,
                    'earnings_this_month' => $dashboard->total_earnings_this_month,
                    'avg_per_session' => $dashboard->total_earnings_this_month > 0
                        ? round($dashboard->total_earnings_this_month / max($dashboard->sessions_this_month, 1), 2)
                        : 0,
                ],
                'availability' => [
                    'hours_available_this_month' => $dashboard->total_hours_available_this_month,
                    'hours_booked_this_month' => $dashboard->total_hours_booked_this_month,
                    'utilization_rate' => round($dashboard->utilization_rate_this_month * 100).'%',
                    'availability_percentage' => $dashboard->getAvailabilityPercentage(),
                    'is_available_soon' => $dashboard->isAvailableSoon(),
                ],
                'specializations' => $dashboard->specializations ?? [],
                'recent_reviews' => array_slice($dashboard->recent_reviews ?? [], 0, 5),
                'last_activity' => $dashboard->last_activity_at?->format('Y-m-d H:i'),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve therapist dashboard', [
                'therapist_id' => $therapist->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get institutional dashboard data
     */
    public function getInstitutionalDashboard(int $institutionId): array
    {
        try {
            $dashboard = $this->institutionalRepo->getWithCache($institutionId);

            if (! $dashboard) {
                Log::warning('Institutional dashboard not found', ['institution_id' => $institutionId]);

                return [];
            }

            $roi = $this->institutionalRepo->calculateROI($institutionId);

            return [
                'institution_id' => $institutionId,
                'overview' => [
                    'type' => $dashboard->institution_type,
                    'total_users' => $dashboard->total_users,
                    'active_users_this_month' => $dashboard->active_users_this_month,
                    'engagement_rate' => round($dashboard->engagement_rate * 100).'%',
                ],
                'sessions' => [
                    'total_completed' => $dashboard->total_sessions_completed,
                    'this_month' => $dashboard->sessions_this_month,
                    'avg_frequency_per_user' => round($dashboard->avg_session_frequency, 2),
                ],
                'wellness' => [
                    'average_wellness_score' => $dashboard->average_wellness_score,
                    'at_risk_users' => $dashboard->at_risk_users,
                    'intervention_success_rate' => round($dashboard->intervention_success_rate * 100).'%',
                    'top_concerns' => $dashboard->getTopConcernsFormatted(),
                ],
                'financial' => [
                    'total_investment' => $dashboard->total_investment,
                    'cost_per_user' => $dashboard->cost_per_user,
                    'roi_percentage' => $roi['roi_percentage'],
                    'estimated_benefit' => $roi['estimated_benefit'],
                    'net_benefit' => $roi['net_benefit'],
                ],
                'contract' => [
                    'status' => $dashboard->getContractStatus(),
                    'start_date' => $dashboard->contract_start_date?->format('Y-m-d'),
                    'end_date' => $dashboard->contract_end_date?->format('Y-m-d'),
                    'days_remaining' => $dashboard->getDaysUntilRenewal(),
                ],
                'risk' => [
                    'retention_risk' => $dashboard->getRetentionRisk(),
                    'needs_support' => $dashboard->shouldPrioritizeSupport(),
                ],
                'satisfaction' => $dashboard->satisfaction_scores ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve institutional dashboard', [
                'institution_id' => $institutionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get admin platform dashboard
     */
    public function getAdminDashboard(): array
    {
        try {
            $dashboard = $this->adminRepo->getWithCache();

            return [
                'overview' => [
                    'total_users' => $dashboard->total_users,
                    'new_users_today' => $dashboard->new_users_today,
                    'active_users_today' => $dashboard->active_users_today,
                    'active_users_this_month' => $dashboard->active_users_this_month,
                    'paying_users' => $dashboard->paying_users,
                    'conversion_rate' => $dashboard->getConversionRate().'%',
                ],
                'therapists' => $this->adminRepo->getTherapistMetrics(),
                'institutions' => $this->adminRepo->getInstitutionalMetrics(),
                'revenue' => $this->adminRepo->getRevenueBreakdown(),
                'growth' => $this->adminRepo->getGrowthMetrics(),
                'sessions' => [
                    'total_completed' => $dashboard->total_sessions_completed,
                    'this_month' => $dashboard->sessions_this_month,
                    'average_rating' => $dashboard->average_session_rating,
                ],
                'system' => $this->adminRepo->getSystemStatus(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve admin dashboard', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get top therapists
     */
    public function getTopTherapists(int $limit = 10): array
    {
        return $this->therapistRepo->getTopRated($limit);
    }

    /**
     * Get highest earning therapists
     */
    public function getHighestEarningTherapists(int $limit = 10): array
    {
        return $this->therapistRepo->getHighestEarners($limit);
    }

    /**
     * Get users needing support
     */
    public function getUsersNeedingSupport(int $limit = 50): array
    {
        return $this->patientRepo->getUsersNeedingSupport($limit);
    }

    /**
     * Get active institutions
     */
    public function getActiveInstitutions(int $limit = 50): array
    {
        return $this->institutionalRepo->getActive($limit);
    }

    /**
     * Refresh all metrics
     */
    public function refreshAllMetrics(): array
    {
        try {
            Log::info('Starting metric refresh');

            $this->adminRepo->updateMetrics();
            $this->institutionalRepo->bulkUpdate();

            Log::info('Metric refresh completed successfully');

            return [
                'success' => true,
                'message' => 'All metrics refreshed',
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to refresh metrics', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
