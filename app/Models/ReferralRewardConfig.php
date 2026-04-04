<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralRewardConfig extends Model
{
    protected $fillable = [
        'referrer_tier',
        'reward_type',
        'reward_value',
        'reward_trigger',
        'is_enabled',
        'max_discount_cap',
        'notes',
    ];

    protected $casts = [
        'reward_value'     => 'decimal:2',
        'max_discount_cap' => 'decimal:2',
        'is_enabled'       => 'boolean',
    ];

    public static function forTier(string $tier): ?self
    {
        return static::where('referrer_tier', $tier)->where('is_enabled', true)->first();
    }
}
