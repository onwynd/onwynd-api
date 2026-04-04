<?php

namespace App\Models;

use App\Models\Therapy\SessionReview;
use App\Models\Therapy\SessionReviewAction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicalAdvisor extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'license_number',
        'credential_type',
        'credentials_file_path',
        'license_expiry_date',
        'specializations',
        'languages',
        'working_hours',
        'timezone',
        'max_reviews_per_day',
        'verification_status',
        'verified_by',
        'verified_at',
        'verification_notes',
        'supervising_director_id',
        'last_training_date',
        'training_completed',
        'phone_number_primary',
        'phone_number_backup',
        'email_primary',
        'email_backup',
        'enable_sms_alerts',
        'enable_push_alerts',
        'enable_email_digest',
        'status',
        'on_leave_until',
        'active_cases_monitoring',
        'reviews_this_month',
        'escalations_this_month',
        'last_active_at',
    ];

    protected $casts = [
        'specializations' => 'json',
        'languages' => 'json',
        'working_hours' => 'json',
        'training_completed' => 'json',
        'license_expiry_date' => 'datetime',
        'verified_at' => 'datetime',
        'last_training_date' => 'datetime',
        'last_active_at' => 'datetime',
        'on_leave_until' => 'datetime',
        'enable_sms_alerts' => 'boolean',
        'enable_push_alerts' => 'boolean',
        'enable_email_digest' => 'boolean',
    ];

    // ============ RELATIONSHIPS ============

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function supervisingDirector()
    {
        return $this->belongsTo(User::class, 'supervising_director_id');
    }

    public function sessionReviews()
    {
        return $this->hasMany(SessionReview::class, 'reviewed_by');
    }

    public function highRiskAssignments()
    {
        return $this->hasMany(ClinicalAdvisorAssignment::class);
    }

    public function reviewActions()
    {
        return $this->hasMany(SessionReviewAction::class);
    }

    // ============ SCOPES ============

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')
            ->where('verification_status', 'verified');
    }

    public function scopeByCredential($query, $credential)
    {
        return $query->where('credential_type', $credential);
    }

    public function scopeBySpecialization($query, $specialization)
    {
        return $query->whereJsonContains('specializations', $specialization);
    }

    // ============ METHODS ============

    /**
     * Check if advisor is currently working (based on schedule)
     */
    public function isCurrentlyWorking(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now();
        $dayOfWeek = strtolower($now->format('l'));
        $currentTime = $now->format('H:i');

        if (! isset($this->working_hours[$dayOfWeek])) {
            return false;
        }

        $schedule = $this->working_hours[$dayOfWeek];

        return $currentTime >= $schedule['start'] && $currentTime <= $schedule['end'];
    }

    /**
     * Check if advisor has capacity for more reviews today
     */
    public function hasCapacityForReview(): bool
    {
        $reviewsToday = $this->sessionReviews()
            ->whereDate('created_at', today())
            ->count();

        return $reviewsToday < $this->max_reviews_per_day;
    }

    /**
     * Get reviews pending action
     */
    public function pendingReviews()
    {
        return $this->sessionReviews()
            ->where('review_status', 'pending')
            ->orderBy('risk_level', 'desc')
            ->orderBy('created_at', 'asc');
    }

    /**
     * Get critical escalations requiring immediate attention
     */
    public function criticalEscalations()
    {
        return $this->sessionReviews()
            ->where('risk_level', 'critical')
            ->where('review_status', '!=', 'approved')
            ->orderBy('created_at', 'asc');
    }

    /**
     * Get high-risk users assigned to this advisor
     */
    public function activeHighRiskUsers()
    {
        return $this->highRiskAssignments()
            ->where('status', 'active')
            ->with('user')
            ->orderBy('priority', 'desc');
    }

    /**
     * Mark as reviewed a session
     */
    public function approveReview(SessionReview $review, ?string $notes = null)
    {
        $review->update([
            'review_status' => 'approved',
            'reviewed_by' => $this->id,
            'reviewed_at' => now(),
            'clinical_advisor_notes' => $notes,
        ]);

        // Log action
        SessionReviewAction::create([
            'session_review_id' => $review->id,
            'clinical_advisor_id' => $this->id,
            'action_type' => 'approved',
            'action_description' => 'Session approved after review',
            'clinical_notes' => $notes,
        ]);

        $this->increment('total_reviews_completed');
        $this->increment('reviews_this_month');

        return $review;
    }

    /**
     * Flag a review for follow-up
     */
    public function flagReview(SessionReview $review, string $reason, string $priority = 'normal')
    {
        $review->update([
            'review_status' => 'flagged',
            'reviewed_by' => $this->id,
            'reviewed_at' => now(),
            'clinical_advisor_notes' => $reason,
        ]);

        SessionReviewAction::create([
            'session_review_id' => $review->id,
            'clinical_advisor_id' => $this->id,
            'action_type' => 'flagged',
            'action_description' => "Review flagged: $reason",
            'clinical_notes' => $reason,
            'priority' => $priority,
        ]);

        $this->increment('total_reviews_completed');
        $this->increment('reviews_this_month');

        return $review;
    }

    /**
     * Escalate to crisis protocol
     */
    public function escalateToCrisis(SessionReview $review, string $reason)
    {
        $review->update([
            'review_status' => 'escalated',
            'reviewed_by' => $this->id,
            'reviewed_at' => now(),
        ]);

        SessionReviewAction::create([
            'session_review_id' => $review->id,
            'clinical_advisor_id' => $this->id,
            'action_type' => 'escalated_to_crisis',
            'action_description' => "Escalated to crisis: $reason",
            'priority' => 'critical',
        ]);

        $this->increment('critical_escalations_handled');
        $this->increment('escalations_this_month');

        // Trigger crisis protocol event if implemented
        // event(new CrisisEscalated($review->user, $reason, $this));

        return $review;
    }

    /**
     * Assign high-risk user for monitoring
     */
    public function assignHighRiskMonitoring(User $user, string $reason, string $priority = 'urgent')
    {
        $assignment = ClinicalAdvisorAssignment::create([
            'clinical_advisor_id' => $this->id,
            'high_risk_user_id' => $user->id,
            'reason' => $reason,
            'priority' => $priority,
            'assigned_date' => today(),
            'status' => 'active',
        ]);

        $this->increment('active_cases_monitoring');

        return $assignment;
    }

    /**
     * Update availability status
     */
    public function setOnLeave(int $days)
    {
        $this->update([
            'status' => 'on_leave',
            'on_leave_until' => now()->addDays($days),
        ]);

        return $this;
    }

    public function returnFromLeave()
    {
        $this->update([
            'status' => 'active',
            'on_leave_until' => null,
        ]);

        return $this;
    }

    /**
     * Check if license is expired
     */
    public function isLicenseExpired(): bool
    {
        return $this->license_expiry_date->isPast();
    }

    /**
     * Get workload summary
     */
    public function getWorkloadSummary()
    {
        return [
            'pending_reviews' => $this->sessionReviews()->where('review_status', 'pending')->count(),
            'critical_escalations' => $this->criticalEscalations()->count(),
            'high_risk_assignments' => $this->activeHighRiskUsers()->count(),
            'reviews_today' => $this->sessionReviews()->whereDate('created_at', today())->count(),
            'capacity_remaining' => max(0, $this->max_reviews_per_day - $this->sessionReviews()->whereDate('created_at', today())->count()),
        ];
    }

    /**
     * Get dashboard metrics
     */
    public function getDashboardMetrics()
    {
        return [
            'workload' => $this->getWorkloadSummary(),
            'performance' => [
                'total_reviews' => $this->total_reviews_completed,
                'avg_review_time' => $this->average_review_time_minutes,
                'escalation_accuracy' => $this->escalation_accuracy_percentage,
                'therapist_satisfaction' => $this->therapist_satisfaction_nps,
            ],
            'status' => [
                'is_active' => $this->status === 'active',
                'is_verified' => $this->verification_status === 'verified',
                'is_currently_working' => $this->isCurrentlyWorking(),
                'license_valid' => ! $this->isLicenseExpired(),
            ],
        ];
    }
}
