<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'session_id',
        'patient_id',
        'therapist_id',
        'rating',
        'review_text',
        'is_anonymous',
        'is_verified',
        'is_published',
        'helpful_count',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'is_verified' => 'boolean',
        'is_published' => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TherapySession::class, 'session_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }
}
