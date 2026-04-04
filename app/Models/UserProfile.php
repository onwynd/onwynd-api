<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'bio',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'country',
        'postal_code',
        'timezone',
        'language',
        'emergency_contact',
        'medical_history',
        'preferences',
        'mbti_type',
        'height_cm',
        'weight_kg',
        'daily_step_goal',
        'onwynd_score_cache',
    ];

    protected $casts = [
        'emergency_contact' => 'array',
        'medical_history' => 'array',
        'preferences' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
