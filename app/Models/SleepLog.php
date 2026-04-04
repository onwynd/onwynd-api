<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SleepLog extends Model
{
    protected $fillable = [
        'user_id',
        'start_time',
        'end_time',
        'duration_minutes',
        'quality_rating',
        'interruptions',
        'notes',
        'sleep_stage_data',
        'source',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'sleep_stage_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
