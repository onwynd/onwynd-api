<?php

namespace App\Models;

use App\Traits\HasUUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasUUID;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'plan_type',
        'price',
        'price_ngn',
        'price_usd',
        'setup_fee_ngn',
        'setup_fee_usd',
        'currency',
        'billing_interval',
        'features',
        'max_sessions',
        'trial_days',
        'is_active',
        'is_popular',
        'is_recommended',
        'best_for',
        'conversion_target',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_ngn' => 'decimal:2',
        'price_usd' => 'decimal:2',
        'setup_fee_ngn' => 'decimal:2',
        'setup_fee_usd' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
        'is_recommended' => 'boolean',
        'conversion_target' => 'integer',
        'sort_order' => 'integer',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
