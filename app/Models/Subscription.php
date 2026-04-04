<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @deprecated Use App\Models\Payment\Subscription instead.
 *
 * This model is a legacy duplicate that references the same `subscriptions` table
 * as App\Models\Payment\Subscription. The Payment\Subscription model is the
 * canonical version with SoftDeletes, proper scopes, and full column coverage.
 *
 * Do not add new references to this class. Migrate existing usages to Payment\Subscription.
 */
class Subscription extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'plan_id',
        'status',
        'current_period_start',
        'current_period_end',
        'cancel_at',
        'cancelled_at',
        'paused_at',
        'paused_until',
        'trial_ends_at',
        'auto_renew',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancel_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'paused_at' => 'datetime',
        'paused_until' => 'datetime',
        'trial_ends_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
