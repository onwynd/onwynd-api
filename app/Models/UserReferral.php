<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UserReferral extends Model
{
    protected $fillable = [
        'uuid',
        'referrer_user_id',
        'referred_user_id',
        'referral_code_id',
        'referrer_tier',
        'status',
        'referee_rewarded_at',
        'referrer_rewarded_at',
    ];

    protected $casts = [
        'referee_rewarded_at'  => 'datetime',
        'referrer_rewarded_at' => 'datetime',
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

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referee()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function referralCode()
    {
        return $this->belongsTo(UserReferralCode::class, 'referral_code_id');
    }

    public function isFullyRewarded(): bool
    {
        return $this->status === 'fully_rewarded';
    }
}
