<?php

namespace App\Models\Therapy;

use App\Models\ClinicalAdvisor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SessionReviewAction extends Model
{
    use HasUuids;

    protected $table = 'session_review_actions';

    protected $fillable = [
        'session_review_id',
        'clinical_advisor_id',
        'action_type',
        'action_description',
        'clinical_notes',
        'priority',
        'action_completed_at',
        'completion_notes',
        'completed_by',
    ];

    protected $casts = [
        'action_completed_at' => 'datetime',
    ];

    // RELATIONSHIPS
    public function sessionReview()
    {
        return $this->belongsTo(SessionReview::class);
    }

    public function clinicalAdvisor()
    {
        return $this->belongsTo(ClinicalAdvisor::class);
    }

    public function completedBy()
    {
        return $this->belongsTo(ClinicalAdvisor::class, 'completed_by');
    }
}
