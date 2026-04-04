<?php

namespace App\Models\Therapy;

use App\Models\ClinicalAdvisor;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SessionReview extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'therapy_session_id',
        'therapist_id',
        'user_id',
        'risk_level',
        'risk_flags',
        'risk_summary',
        'recommended_action',
        'risk_confidence_score',
        'empathy_score',
        'clinical_accuracy_score',
        'directiveness_score',
        'pacing_score',
        'overall_session_quality_score',
        'strengths',
        'opportunities',
        'peer_comparison',
        'predicted_improvement_percentage',
        'outcome_confidence_score',
        'success_factors',
        'risk_factors',
        'treatment_alignment_score',
        'addressed_treatment_goals',
        'homework_completed',
        'recommendations',
        'compliance_score',
        'compliance_flags',
        'compliance_notes',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'clinical_advisor_notes',
        'ai_model_used',
        'processing_time_seconds',
        'full_ai_response',
    ];

    protected $casts = [
        'risk_flags' => 'json',
        'strengths' => 'json',
        'opportunities' => 'json',
        'peer_comparison' => 'json',
        'success_factors' => 'json',
        'risk_factors' => 'json',
        'recommendations' => 'json',
        'compliance_flags' => 'json',
        'full_ai_response' => 'json',
        'reviewed_at' => 'datetime',
        'addressed_treatment_goals' => 'boolean',
        'homework_completed' => 'boolean',
    ];

    public function therapySession()
    {
        return $this->belongsTo(TherapySession::class);
    }

    public function therapist()
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(ClinicalAdvisor::class, 'reviewed_by');
    }

    public function actions()
    {
        return $this->hasMany(SessionReviewAction::class);
    }
}
