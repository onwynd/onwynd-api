<?php

namespace App\Models\Dashboard;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * InstitutionalDashboard Model
 *
 * Aggregates institutional partner metrics (corporations, universities, NGOs)
 * Provides B2B insights: employee/student engagement, ROI, health metrics, liability
 *
 * @property int $id
 * @property int $institution_id Organization ID from partner/institution model
 * @property string $institution_type Enum: 'corporate', 'university', 'ngo', 'faith_org'
 * @property int $total_users Total enrolled users from institution
 * @property int $active_users_this_month Users with activity in last 30 days
 * @property float $engagement_rate Percentage of users actively using platform
 * @property int $total_sessions_completed Lifetime sessions from institution users
 * @property int $sessions_this_month Sessions this month
 * @property float $avg_session_frequency Avg sessions per user per month
 * @property array $health_metrics Aggregated wellness data
 * @property float $average_wellness_score Institution average wellness score
 * @property array $concern_breakdown Distribution of primary concerns
 * @property int $at_risk_users Count of users showing declining trends
 * @property float $intervention_success_rate % of at-risk users improved
 * @property float $total_investment Total amount paid for subscriptions
 * @property float $cost_per_user ARPU (annual revenue per user)
 * @property float $estimated_roi Estimated return on investment
 * @property array $absenteeism_impact Estimated sick days prevented
 * @property array $satisfaction_scores NPS and CSAT
 * @property array $top_concerns Top 5 mental health concerns in cohort
 * @property \Carbon\Carbon $contract_start_date
 * @property \Carbon\Carbon $contract_end_date
 * @property string $contract_status 'active', 'expired', 'renewal_due'
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class InstitutionalDashboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'institution_type',
        'total_users',
        'active_users_this_month',
        'engagement_rate',
        'total_sessions_completed',
        'sessions_this_month',
        'avg_session_frequency',
        'health_metrics',
        'average_wellness_score',
        'concern_breakdown',
        'at_risk_users',
        'intervention_success_rate',
        'total_investment',
        'cost_per_user',
        'estimated_roi',
        'absenteeism_impact',
        'satisfaction_scores',
        'top_concerns',
        'contract_start_date',
        'contract_end_date',
        'contract_status',
    ];

    protected $casts = [
        'health_metrics' => 'json',
        'concern_breakdown' => 'json',
        'absenteeism_impact' => 'json',
        'satisfaction_scores' => 'json',
        'top_concerns' => 'json',
        'contract_start_date' => 'datetime',
        'contract_end_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $table = 'institutional_dashboards';

    // Relationships
    public function institution(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Institutional\Organization::class, 'institution_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('contract_status', 'active')
            ->where('contract_end_date', '>', now());
    }

    public function scopeHighEngagement($query, float $minRate = 0.6)
    {
        return $query->where('engagement_rate', '>=', $minRate);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('institution_type', $type);
    }

    public function scopeRenewalDue($query)
    {
        return $query->where('contract_status', 'renewal_due')
            ->where('contract_end_date', '<=', now()->addDays(90));
    }

    // Helper methods
    public function isContractActive(): bool
    {
        return $this->contract_status === 'active' &&
               $this->contract_end_date &&
               $this->contract_end_date->isFuture();
    }

    public function getDaysUntilRenewal(): ?int
    {
        if (! $this->contract_end_date) {
            return null;
        }

        $days = $this->contract_end_date->diffInDays(now());

        return $days > 0 ? $days : 0;
    }

    public function getContractStatus(): string
    {
        if (! $this->isContractActive()) {
            return 'Expired';
        }

        $daysLeft = $this->getDaysUntilRenewal();

        if ($daysLeft <= 30) {
            return 'Renewal Urgent';
        } elseif ($daysLeft <= 90) {
            return 'Renewal Due';
        }

        return 'Active';
    }

    public function getROIPercentage(): float
    {
        return $this->estimated_roi ? round($this->estimated_roi, 2) : 0;
    }

    public function getEstimatedAbsenteeismSavings(): float
    {
        if (! $this->absenteeism_impact) {
            return 0;
        }

        $daysPrevent = $this->absenteeism_impact['days_prevented'] ?? 0;
        $costPerDay = 5000; // Estimated daily productivity cost in Naira

        return $daysPrevent * $costPerDay;
    }

    public function getRetentionRisk(): string
    {
        if ($this->engagement_rate >= 0.7 && $this->intervention_success_rate >= 0.6) {
            return 'Low Risk';
        } elseif ($this->engagement_rate >= 0.5) {
            return 'Medium Risk';
        }

        return 'High Risk - Immediate Action Needed';
    }

    public function getTopConcernsFormatted(): array
    {
        if (! $this->top_concerns) {
            return [];
        }

        return collect($this->top_concerns)
            ->map(fn ($concern) => "{$concern['name']} ({$concern['count']} users)")
            ->values()
            ->toArray();
    }

    public function getMonthlyGrowthRate(): float
    {
        if ($this->total_users === 0) {
            return 0;
        }

        return round(($this->active_users_this_month / $this->total_users) * 100, 2);
    }

    public function shouldPrioritizeSupport(): bool
    {
        return $this->at_risk_users > ($this->total_users * 0.1) ||
               $this->engagement_rate < 0.4 ||
               $this->getRetentionRisk() === 'High Risk - Immediate Action Needed';
    }
}
