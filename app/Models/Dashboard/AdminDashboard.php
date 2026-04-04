<?php

namespace App\Models\Dashboard;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * AdminDashboard Model
 *
 * Platform-wide analytics and management dashboard for administrators
 * Tracks business health, platform metrics, user growth, revenue, and operational KPIs
 *
 * @property int $id
 * @property int $total_users Total registered users
 * @property int $total_therapists Total registered therapists
 * @property int $new_users_today New registrations today
 * @property int $active_users_today Daily active users
 * @property int $active_users_this_month Monthly active users
 * @property int $paying_users Subscribed/premium users
 * @property float $d2c_revenue_total D2C subscription revenue total
 * @property float $d2c_revenue_this_month D2C revenue (current month)
 * @property float $b2b_revenue_total B2B contracts revenue total
 * @property float $b2b_revenue_this_month B2B revenue (current month)
 * @property float $marketplace_revenue_total Therapist marketplace commission total
 * @property float $marketplace_revenue_this_month Marketplace revenue (current month)
 * @property float $total_platform_revenue All revenue streams combined
 * @property int $total_sessions_completed Total therapy sessions ever
 * @property int $sessions_this_month Sessions this month
 * @property float $average_session_rating Average session rating
 * @property int $total_institutions B2B institutional partners
 * @property int $active_institutions Institutions with active contracts
 * @property int $physical_centers Number of wellness centers
 * @property int $total_customers_from_institutions Total users via B2B
 * @property array $platform_metrics Growth trends, churn, NPS
 * @property array $top_therapists Highest rated/earning therapists
 * @property array $top_institutions Largest/most engaged partners
 * @property float $system_health_score Overall platform health (0-100)
 * @property array $alerts_critical Critical issues needing attention
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AdminDashboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'total_users',
        'total_therapists',
        'new_users_today',
        'active_users_today',
        'active_users_this_month',
        'paying_users',
        'd2c_revenue_total',
        'd2c_revenue_this_month',
        'b2b_revenue_total',
        'b2b_revenue_this_month',
        'marketplace_revenue_total',
        'marketplace_revenue_this_month',
        'total_platform_revenue',
        'total_sessions_completed',
        'sessions_this_month',
        'average_session_rating',
        'total_institutions',
        'active_institutions',
        'physical_centers',
        'total_customers_from_institutions',
        'platform_metrics',
        'top_therapists',
        'top_institutions',
        'system_health_score',
        'alerts_critical',
    ];

    protected $casts = [
        'platform_metrics' => 'json',
        'top_therapists' => 'json',
        'top_institutions' => 'json',
        'alerts_critical' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $table = 'admin_dashboards';

    // Helper methods
    public function getConversionRate(): float
    {
        if ($this->total_users === 0) {
            return 0;
        }

        return round(($this->paying_users / $this->total_users) * 100, 2);
    }

    public function getUserGrowthRate(): float
    {
        if (! $this->platform_metrics || ! isset($this->platform_metrics['user_growth_rate'])) {
            return 0;
        }

        return $this->platform_metrics['user_growth_rate'];
    }

    public function getMonthlyRevenueGrowth(): float
    {
        if (! $this->platform_metrics || ! isset($this->platform_metrics['mrr_growth_rate'])) {
            return 0;
        }

        return $this->platform_metrics['mrr_growth_rate'];
    }

    public function getDailyActiveUserRate(): float
    {
        if ($this->active_users_this_month === 0) {
            return 0;
        }

        return round(($this->active_users_today / $this->active_users_this_month) * 100, 2);
    }

    public function getAverageRevenuePerUser(): float
    {
        if ($this->paying_users === 0) {
            return 0;
        }

        return round($this->d2c_revenue_this_month / $this->paying_users, 2);
    }

    public function getRevenueBreakdown(): array
    {
        $total = $this->total_platform_revenue;

        if ($total === 0) {
            return [
                'd2c_percentage' => 0,
                'b2b_percentage' => 0,
                'marketplace_percentage' => 0,
            ];
        }

        return [
            'd2c_percentage' => round(($this->d2c_revenue_this_month / $total) * 100, 2),
            'b2b_percentage' => round(($this->b2b_revenue_this_month / $total) * 100, 2),
            'marketplace_percentage' => round(($this->marketplace_revenue_this_month / $total) * 100, 2),
        ];
    }

    public function getSystemHealth(): string
    {
        if ($this->system_health_score >= 90) {
            return 'Excellent';
        } elseif ($this->system_health_score >= 75) {
            return 'Good';
        } elseif ($this->system_health_score >= 60) {
            return 'Fair';
        }

        return 'Critical';
    }

    public function hasAlerts(): bool
    {
        return count($this->alerts_critical ?? []) > 0;
    }

    public function getCriticalAlerts(): array
    {
        return $this->alerts_critical ?? [];
    }

    public function getInstitutionActivationRate(): float
    {
        if ($this->total_institutions === 0) {
            return 0;
        }

        return round(($this->active_institutions / $this->total_institutions) * 100, 2);
    }

    public function getTherapistEngagement(): array
    {
        return [
            'total' => $this->total_therapists,
            'active_this_month' => $this->sessions_this_month > 0 ? $this->total_therapists : 0,
            'average_sessions_per_therapist' => $this->total_therapists > 0
                ? round($this->sessions_this_month / $this->total_therapists, 2)
                : 0,
            'average_earning_per_therapist' => $this->total_therapists > 0
                ? round($this->marketplace_revenue_this_month / $this->total_therapists, 2)
                : 0,
        ];
    }

    public function getMonthlyMetrics(): array
    {
        return [
            'revenue' => $this->total_platform_revenue,
            'new_users' => $this->new_users_today,
            'active_users' => $this->active_users_this_month,
            'sessions' => $this->sessions_this_month,
            'average_rating' => $this->average_session_rating,
            'conversion_rate' => $this->getConversionRate(),
        ];
    }
}
