<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UserReferralCode extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'tier',
        'uses_count',
        'is_active',
    ];

    protected $casts = [
        'uses_count' => 'integer',
        'is_active'  => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->code)) {
                // Generate a short, human-friendly code: REF-XXXXXXXX
                $model->code = 'REF'.strtoupper(Str::random(8));
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function referrals()
    {
        return $this->hasMany(UserReferral::class, 'referral_code_id');
    }
}
