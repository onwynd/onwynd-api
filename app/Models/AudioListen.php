<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AudioListen extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'track_id',
        'track_title',
        'track_category',
        'duration_seconds',
        'completed',
        'listened_at',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'listened_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
