<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoodLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'patient_id',
        'mood_score', // 1-10 or 1-5
        'emotions', // Array of tags like 'happy', 'anxious'
        'notes',
        'activities', // What were they doing?
        'sleep_hours',
        'weather_data', // Context
    ];

    protected $casts = [
        'emotions' => 'array',
        'activities' => 'array',
        'weather_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
