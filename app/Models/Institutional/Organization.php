<?php

namespace App\Models\Institutional;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'industry',
        'domain',
        'sso_config',
        'branding',
        'contact_email',
        'country',
        'city',
        'total_employees',
        'onboarded_count',
        'status',
        'subscription_plan',
        'max_members',
        'relationship_manager_id',
        'contracted_seats',
        'current_seats',
        'org_type',
        'subscription_expires_at',
        'grace_period_days',
        'paywall_active',
        // University configuration fields
        'funding_model',
        'billing_cycle',
        'semester_start_month',
        'semester_2_start_month',
        'session_credits_per_student',
        'session_ceiling_ngn',
        'domain_auto_join',
        'university_domain',
        'student_id_verification',
        'crisis_notification_email',
        'early_crisis_detection',
    ];

    protected $casts = [
        'sso_config' => 'array',
        'branding' => 'array',
        'semester_start_month' => 'integer',
        'semester_2_start_month' => 'integer',
        'session_credits_per_student' => 'integer',
        'session_ceiling_ngn' => 'integer',
        'domain_auto_join' => 'boolean',
        'student_id_verification' => 'boolean',
        'early_crisis_detection' => 'boolean',
    ];

    public function members()
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class, 'organization_members');
    }

    public function relationshipManager()
    {
        return $this->belongsTo(\App\Models\User::class, 'relationship_manager_id');
    }
}
