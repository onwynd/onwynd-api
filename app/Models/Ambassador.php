<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Ambassador extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'referral_code',
        'status',
        'commission_rate',
        'reason',
        'experience',
        'social_media',
        'total_referrals',
        'active_referrals',
        'total_earnings',
        'current_month_referrals',
        'rank',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class);
    }

    public function payouts()
    {
        return $this->hasMany(AmbassadorPayout::class);
    }

    public function referralCode()
    {
        return $this->hasOne(ReferralCode::class);
    }
}
