<?php

namespace App\Models\Dashboard;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PatientDashboard Model
 *
 * Aggregates patient-specific metrics for wellness journey tracking
 * Tracks progress, streaks, goals, session frequency, and health outcomes
 *
 * @property int $id
 * @property int $user_id
 * @property string $current_mood Current mood (1-5 scale)
 * @property int $current_streak Days of consecutive app engagement
 * @property int $longest_streak Best streak achieved
 * @property int $sessions_completed Total therapy sessions
 * @property int $sessions_this_month Sessions this month
 * @property int $pending_sessions_booked Upcoming sessions
 * @property string $primary_concern Main issue (anxiety, depression, etc.)
 * @property array $active_goals Current health goals
 * @property array $goal_progress Progress on each goal (0-100)
 * @property float $overall_progress Overall health journey progress
 * @property int $ai_check_ins Daily AI chatbot interactions completed
 * @property int $peer_messages_sent Messages in peer communities
 * @property int $meditation_minutes Total meditation minutes logged
 * @property int $community_participations Posts/comments in community
 * @property string $subscription_status Status: 'free', 'premium', 'recovery_program'
 * @property \Carbon\Carbon $subscription_expires_at
 * @property array $mood_history Last 30 days mood data
 * @property array $insight_tags Behavioral insights/patterns
 * @property float $engagement_score Overall engagement metric
 * @property \Carbon\Carbon $last_session_at
 * @property \Carbon\Carbon $next_session_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PatientDashboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'current_mood',
        'current_streak',
        'longest_streak',
        'sessions_completed',
        'sessions_this_month',
        'pending_sessions_booked',
        'primary_concern',
        'active_goals',
        'goal_progress',
        'overall_progress',
        'ai_check_ins',
        'peer_messages_sent',
        'meditation_minutes',
        'community_participations',
        'subscription_status',
        'subscription_expires_at',
        'mood_history',
        'insight_tags',
        'engagement_score',
        'last_session_at',
        'next_session_at',
    ];

    protected $casts = [
        'active_goals' => 'json',
        'goal_progress' => 'json',
        'mood_history' => 'json',
        'insight_tags' => 'json',
        'subscription_expires_at' => 'datetime',
        'last_session_at' => 'datetime',
        'next_session_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $table = 'patient_dashboards';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActiveEngagement($query, int $minimumStreak = 7)
    {
        return $query->where('current_streak', '>=', $minimumStreak);
    }

    public function scopeHighProgress($query, float $minProgress = 60)
    {
        return $query->where('overall_progress', '>=', $minProgress);
    }

    public function scopeByPrimaryConcern($query, string $concern)
    {
        return $query->where('primary_concern', $concern);
    }

    public function scopeSubscribed($query)
    {
        return $query->whereIn('subscription_status', ['premium', 'recovery_program']);
    }

    // Helper methods
    public function isStreakActive(): bool
    {
        return $this->current_streak > 0;
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscription_status !== 'free' &&
               $this->subscription_expires_at &&
               $this->subscription_expires_at->isFuture();
    }

    public function getSubscriptionDaysRemaining(): ?int
    {
        if (! $this->subscription_expires_at) {
            return null;
        }

        return $this->subscription_expires_at->diffInDays(now());
    }

    public function getGoalCompletionCount(): int
    {
        if (! $this->goal_progress) {
            return 0;
        }

        return collect($this->goal_progress)->filter(fn ($progress) => $progress >= 100)->count();
    }

    public function getMoodTrend(): string
    {
        if (! $this->mood_history || count($this->mood_history) < 2) {
            return 'stable';
        }

        $recent = collect($this->mood_history)->sortByDesc('date')->take(7);
        $oldMoods = $recent->filter(fn ($m, $k) => $k >= 3)->avg('value') ?? 3;
        $newMoods = $recent->filter(fn ($m, $k) => $k < 3)->avg('value') ?? 3;

        if ($newMoods > $oldMoods + 0.5) {
            return 'improving';
        } elseif ($newMoods < $oldMoods - 0.5) {
            return 'declining';
        }

        return 'stable';
    }

    public function getWellnessScore(): float
    {
        $components = [
            'overall_progress' => $this->overall_progress * 0.3,
            'engagement' => $this->engagement_score * 0.3,
            'streak' => min($this->current_streak / 30, 5) * 0.2,
            'sessions' => min($this->sessions_this_month / 4, 5) * 0.2,
        ];

        return array_sum($components);
    }

    public function getNeedsSupportAlert(): bool
    {
        return $this->getMoodTrend() === 'declining' ||
               ($this->current_mood !== null && $this->current_mood <= 2) ||
               $this->current_streak === 0;
    }

    public function getRecommendedActions(): array
    {
        $actions = [];

        if ($this->current_streak === 0) {
            $actions[] = 'Resume daily check-ins';
        }

        if ($this->sessions_this_month < 2 && $this->hasActiveSubscription()) {
            $actions[] = 'Book a therapy session';
        }

        if ($this->meditation_minutes < 60) {
            $actions[] = 'Try a guided meditation';
        }

        if ($this->getMoodTrend() === 'declining') {
            $actions[] = 'Connect with your therapist';
        }

        return $actions;
    }
}
