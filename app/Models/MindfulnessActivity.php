<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MindfulnessActivity extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'type',
        'duration_seconds',
        'completed_at',
        'notes',
        'audio_file_path',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
