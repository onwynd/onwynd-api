<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalStep extends Model
{
    protected $fillable = [
        'approval_request_id', 'step_number', 'step_label',
        'approver_role', 'approver_id',
        'status', 'actioned_by', 'action_notes', 'submitter_response',
        'actioned_at', 'due_at', 'escalation_notified',
    ];

    protected $casts = [
        'actioned_at'          => 'datetime',
        'due_at'               => 'datetime',
        'escalation_notified'  => 'boolean',
    ];

    public function request()
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function actionedBy()
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }

    public function isPending(): bool     { return $this->status === 'pending'; }
    public function isUnderReview(): bool { return $this->status === 'under_review'; }
    public function isOverdue(): bool     { return $this->due_at && $this->due_at->isPast() && $this->isPending(); }
}
