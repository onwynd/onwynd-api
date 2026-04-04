<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicalAdvisorAssignment extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'clinical_advisor_assignments';

    protected $fillable = [
        'clinical_advisor_id',
        'high_risk_user_id',
        'reason',
        'priority',
        'assigned_date',
        'review_date',
        'check_in_frequency_days',
        'last_check_in',
        'next_check_in',
        'monitoring_notes',
        'status',
        'assignment_end_date',
        'closure_notes',
    ];

    protected $casts = [
        'assigned_date' => 'date',
        'review_date' => 'date',
        'last_check_in' => 'datetime',
        'next_check_in' => 'datetime',
        'assignment_end_date' => 'date',
        'monitoring_notes' => 'json',
    ];

    // RELATIONSHIPS
    public function clinicalAdvisor()
    {
        return $this->belongsTo(ClinicalAdvisor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'high_risk_user_id');
    }

    // SCOPES
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeUrgent($query)
    {
        return $query->whereIn('priority', ['urgent', 'critical']);
    }

    // METHODS
    public function closeAssignment(string $notes)
    {
        $this->update([
            'status' => 'closed',
            'assignment_end_date' => today(),
            'closure_notes' => $notes,
        ]);

        $this->clinicalAdvisor->decrement('active_cases_monitoring');
    }

    public function escalate(string $reason)
    {
        $this->update(['status' => 'escalated']);
        // event(new HighRiskUserEscalated($this->user, $reason, $this->clinicalAdvisor));
    }
}
