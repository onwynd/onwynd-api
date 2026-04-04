<?php

namespace App\Models\Institutional;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationMember extends Model
{
    use HasFactory;

    protected $appends = ['sessions_remaining'];

    protected $fillable = [
        'organization_id',
        'user_id',
        'role',
        'employee_id',
        'department',
        'sessions_used_this_month',
        'sessions_limit',
        'session_duration_minutes',
        'last_reset_at',
    ];

    protected $casts = [
        'sessions_used_this_month' => 'integer',
        'sessions_limit' => 'integer',
        'session_duration_minutes' => 'integer',
        'last_reset_at' => 'datetime',
    ];

    /**
     * Computed accessor: sessions_remaining
     *
     * Canonical way to read how many sessions a member has left this month.
     * The plan references organisations.sessions_remaining but the actual data
     * lives here as (sessions_limit - sessions_used_this_month). This accessor
     * bridges that gap so all frontend/notification code reads the same value.
     *
     * Usage: $member->sessions_remaining
     */
    public function getSessionsRemainingAttribute(): int
    {
        return max(0, ($this->sessions_limit ?? 0) - ($this->sessions_used_this_month ?? 0));
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
