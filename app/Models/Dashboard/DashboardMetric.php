<?php

namespace App\Models\Dashboard;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DashboardMetric Model
 *
 * Stores real-time dashboard metrics for users, therapists, and institutions
 * Supports caching and rapid retrieval of dashboard statistics
 *
 * @property int $id
 * @property int $user_id
 * @property string $metric_type Enum: 'user', 'therapist', 'institutional', 'admin'
 * @property string $metric_key Unique identifier: 'sessions_completed', 'revenue_total', etc.
 * @property mixed $metric_value Current value
 * @property string $period Enum: 'daily', 'weekly', 'monthly', 'yearly'
 * @property \Carbon\Carbon $period_start
 * @property \Carbon\Carbon $period_end
 * @property array $previous_value Previous period value for trend calculation
 * @property float $change_percentage Percentage change from previous period
 * @property array $metadata Additional context
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DashboardMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'metric_type',
        'metric_key',
        'metric_value',
        'period',
        'period_start',
        'period_end',
        'previous_value',
        'change_percentage',
        'metadata',
    ];

    protected $casts = [
        'metric_value' => 'json',
        'previous_value' => 'json',
        'metadata' => 'json',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
    ];

    protected $table = 'dashboard_metrics';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByType($query, string $type)
    {
        return $query->where('metric_type', $type);
    }

    public function scopeByKey($query, string $key)
    {
        return $query->where('metric_key', $key);
    }

    public function scopeByPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCurrentPeriod($query)
    {
        return $query->where('period_end', '>=', now());
    }

    // Helper methods
    public function hasTrend(): bool
    {
        return $this->change_percentage !== null;
    }

    public function isTrendingUp(): bool
    {
        return $this->hasTrend() && $this->change_percentage > 0;
    }

    public function isTrendingDown(): bool
    {
        return $this->hasTrend() && $this->change_percentage < 0;
    }

    public function getTrendLabel(): string
    {
        if (! $this->hasTrend()) {
            return 'No data';
        }

        $trend = $this->isTrendingUp() ? 'up' : 'down';
        $percentage = abs($this->change_percentage);

        return "{$trend} {$percentage}%";
    }
}
