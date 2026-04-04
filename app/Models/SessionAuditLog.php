<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionAuditLog extends Model
{
    protected $fillable = [
        'session_uuid',
        'room_name',
        'transcript',
        'segments',
        'audit_status',
        'risk_score',
        'violations',
        'reviewed',
        'reviewed_by',
        'reviewer_notes',
        'reviewed_at',
        'agent_version',
        'duration_seconds',
        'error_message',
    ];

    protected $casts = [
        'segments'      => 'array',
        'violations'    => 'array',
        'risk_score'    => 'float',
        'reviewed'      => 'boolean',
        'reviewed_at'   => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TherapySession::class, 'session_uuid', 'uuid');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isFlagged(): bool
    {
        return $this->audit_status === 'flagged';
    }

    public function isClean(): bool
    {
        return $this->audit_status === 'clean';
    }

    /** Highest-severity violation type, or null if clean. */
    public function topViolation(): ?string
    {
        if (empty($this->violations)) return null;
        $sorted = collect($this->violations)->sortByDesc(fn($v) => match($v['severity'] ?? '') {
            'critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1, default => 0,
        });
        return $sorted->first()['type'] ?? null;
    }
}
