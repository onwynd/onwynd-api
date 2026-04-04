<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReferralCode extends Model
{
    protected $fillable = [
        'ambassador_id',
        'code',
        'expires_at',
        'uses_count',
        'max_uses',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'uses_count' => 'integer',
        'max_uses' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->code)) {
                $model->code = Str::upper(Str::random(8));
            }
        });
    }

    public function ambassador()
    {
        return $this->belongsTo(Ambassador::class);
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'ambassador_id', 'ambassador_id');
    }

    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isMaxedOut()
    {
        return $this->max_uses && $this->uses_count >= $this->max_uses;
    }

    public function isValid()
    {
        return ! $this->isExpired() && ! $this->isMaxedOut();
    }
}
