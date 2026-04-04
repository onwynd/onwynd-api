<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionNote extends Model
{
    protected $fillable = [
        'session_id',
        'therapist_id',
        'session_summary',
        'observations',
        'treatment_plan',
        'next_steps',
        'private_notes',
        'is_shared_with_patient',
    ];

    protected $casts = [
        'is_shared_with_patient' => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TherapySession::class, 'session_id');
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }
}
