<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoodTracking extends Model
{
    protected $fillable = [
        'user_id',
        'mood_level',
        'mood_tags',
        'notes',
        'logged_at',
    ];

    protected $casts = [
        'mood_tags' => 'array',
        'logged_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
