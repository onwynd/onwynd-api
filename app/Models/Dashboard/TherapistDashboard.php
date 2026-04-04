<?php

namespace App\Models\Dashboard;

use App\Models\Therapist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TherapistDashboard Model
 *
 * Aggregates therapist-specific metrics for real-time dashboard display
 * Tracks earnings, patient engagement, session performance, and availability
 *
 * @property int $id
 * @property int $therapist_id
 * @property int $user_id
 * @property int $total_patients Current active patient count
 * @property int $sessions_completed_total Lifetime sessions completed
 * @property int $sessions_this_month Sessions completed this month
 * @property int $pending_sessions Upcoming confirmed sessions
 * @property float $total_earnings_lifetime Total earnings (Naira)
 * @property float $total_earnings_this_month Month-to-date earnings
 * @property float $average_rating Current average patient rating (1-5)
 * @property int $total_ratings Count of patient ratings
 * @property float $response_time_hours Average response time to messages
 * @property float $patient_satisfaction_percentage Percentage satisfied (based on reviews)
 * @property int $total_hours_available_this_month
 * @property int $total_hours_booked_this_month
 * @property float $utilization_rate_this_month Hours booked / Hours available
 * @property float $avg_session_duration_minutes
 * @property array $specializations
 * @property array $recent_reviews Last 5 patient reviews
 * @property \Carbon\Carbon $last_activity_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TherapistDashboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'therapist_id',
        'user_id',
        'total_patients',
        'sessions_completed_total',
        'sessions_this_month',
        'pending_sessions',
        'total_earnings_lifetime',
        'total_earnings_this_month',
        'average_rating',
        'total_ratings',
        'response_time_hours',
        'patient_satisfaction_percentage',
        'total_hours_available_this_month',
        'total_hours_booked_this_month',
        'utilization_rate_this_month',
        'avg_session_duration_minutes',
        'specializations',
        'recent_reviews',
        'last_activity_at',
    ];

    protected $casts = [
        'specializations' => 'json',
        'recent_reviews' => 'json',
        'last_activity_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $table = 'therapist_dashboards';

    // Relationships
    public function therapist(): BelongsTo
    {
        return $this->belongsTo(Therapist::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeTopRated($query, int $limit = 10)
    {
        return $query->orderByDesc('average_rating')->limit($limit);
    }

    public function scopeMostActive($query, int $limit = 10)
    {
        return $query->orderByDesc('sessions_completed_total')->limit($limit);
    }

    public function scopeHighestEarners($query, int $limit = 10)
    {
        return $query->orderByDesc('total_earnings_this_month')->limit($limit);
    }

    public function scopeWithHighUtilization($query, float $minRate = 0.7)
    {
        return $query->where('utilization_rate_this_month', '>=', $minRate);
    }

    // Helper methods
    public function getEarningsGrowthThisMonth(): float
    {
        return $this->total_earnings_this_month;
    }

    public function getNextSessionScheduled()
    {
        return optional($this->therapist)
            ->sessions()
            ->upcoming()
            ->first();
    }

    public function isAvailableSoon(): bool
    {
        return $this->pending_sessions > 0 || $this->total_hours_available_this_month > 0;
    }

    public function getAvailabilityPercentage(): float
    {
        if ($this->total_hours_available_this_month === 0) {
            return 0;
        }

        return ($this->total_hours_available_this_month /
                ($this->total_hours_available_this_month + $this->total_hours_booked_this_month)) * 100;
    }

    public function isTopPerformer(): bool
    {
        return $this->average_rating >= 4.5 && $this->sessions_completed_total >= 20;
    }

    public function getPerformanceRank(): string
    {
        if ($this->average_rating >= 4.8) {
            return 'Elite';
        } elseif ($this->average_rating >= 4.5) {
            return 'Top Tier';
        } elseif ($this->average_rating >= 4.0) {
            return 'Excellent';
        } elseif ($this->average_rating >= 3.5) {
            return 'Good';
        }

        return 'Developing';
    }
}
