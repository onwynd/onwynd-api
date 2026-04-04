<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotaSetting extends Model
{
    protected $table = 'quota_settings';

    protected $fillable = [
        'free_daily_activities',
        'free_ai_messages',
        'new_user_ai_messages',
        'new_user_days',
        'distress_extension_messages',
        'abuse_cap_messages',
        'corporate_grace_period_days',
    ];

    protected $casts = [
        'free_daily_activities' => 'integer',
        'free_ai_messages' => 'integer',
        'new_user_ai_messages' => 'integer',
        'new_user_days' => 'integer',
        'distress_extension_messages' => 'integer',
        'abuse_cap_messages' => 'integer',
        'corporate_grace_period_days' => 'integer',
    ];
}
