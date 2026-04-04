<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SleepSchedule extends Model
{
    protected $fillable = [
        'user_id',
        'target_bedtime',
        'target_wake_time',
        'reminder_enabled',
        'reminder_minutes_before',
        'days_of_week',
    ];

    protected $casts = [
        'reminder_enabled' => 'boolean',
        'days_of_week' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
