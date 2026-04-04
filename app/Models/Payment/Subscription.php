<?php

namespace App\Models\Payment;

use App\Models\Plan;
use App\Models\User;
use App\Traits\HasUUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasUUID, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'subscriptions';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'expires_at',
        'pause_until',
        'renewal_count',
        'payment_method_id',
        'stripe_subscription_id',
        'amount',
        'currency',
        'auto_renew',
        'cancellation_reason',
        'canceled_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'pause_until' => 'datetime',
        'canceled_at' => 'datetime',
        'auto_renew' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the plan for the subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Check if subscription is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast() && $this->status === 'active';
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    /**
     * Check if subscription is paused
     */
    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    /**
     * Check if subscription is canceled
     */
    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    /**
     * Get days until expiration
     */
    public function daysUntilExpiration(): int
    {
        return now()->diffInDays($this->expires_at, false);
    }

    /**
     * Scope: Only active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope: Only expired subscriptions
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
            ->where('status', 'active');
    }

    /**
     * Scope: Expiring soon
     */
    public function scopeExpiringWithin($query, int $days)
    {
        return $query->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now())
            ->where('status', 'active');
    }

    /**
     * Scope: Paused subscriptions ready to resume
     */
    public function scopeReadyToResume($query)
    {
        return $query->where('status', 'paused')
            ->where('pause_until', '<=', now());
    }
}
