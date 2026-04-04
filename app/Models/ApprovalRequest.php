<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ApprovalRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'type', 'title', 'description',
        'subject_type', 'subject_id',
        'requested_by', 'current_step', 'total_steps',
        'status', 'metadata', 'resolved_at',
    ];

    protected $casts = [
        'metadata'    => 'array',
        'resolved_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->uuid ??= (string) Str::uuid());
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function steps()
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('step_number');
    }

    public function currentStepRecord()
    {
        return $this->hasOne(ApprovalStep::class)
                    ->where('step_number', $this->current_step);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function subject()
    {
        return $this->morphTo();
    }

    // ── Computed ────────────────────────────────────────────────────────────────

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }

    public function progressPct(): int
    {
        if ($this->total_steps === 0) return 100;
        return (int) round(($this->current_step - 1) / $this->total_steps * 100);
    }
}
