<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'type',
        'audio_url',
        'duration_seconds',
        'mood_emoji',
        'stress_level',
        'emotions',
        'tags',
        'is_private',
        'ai_analysis',
        'crisis_detected',
    ];

    protected $casts = [
        'emotions' => 'array',
        'tags' => 'array',
        'ai_analysis' => 'array',
        'is_private' => 'boolean',
        'crisis_detected' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
