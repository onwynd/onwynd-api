<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReferralReward extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'referral_id',
        'amount',
        'currency',
        'type',
        'status',
        'issued_at',
        'expires_at',
        'redeemed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'redeemed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->issued_at)) {
                $model->issued_at = now();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function referral()
    {
        return $this->belongsTo(Referral::class);
    }

    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isRedeemable()
    {
        return $this->status === 'pending' && ! $this->isExpired();
    }

    public function markAsRedeemed()
    {
        $this->update([
            'status' => 'paid',
            'redeemed_at' => now(),
        ]);
    }
}
