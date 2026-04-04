<?php

namespace App\Models;

use App\Mail\ConfirmEmail;
use App\Models\Gamification\Badge;
use App\Models\Gamification\Streak;
use App\Models\Gamification\UserChallengeProgress;
use App\Models\Institutional\OrganizationMember;
use App\Models\Therapy\MatchingPreference;
use App\Models\Therapy\TherapistSpecialty;
use App\Models\NotificationSetting;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'role_id',
        'first_name',
        'last_name',
        'display_name',
        'email',
        'password',
        'phone',
        'date_of_birth',
        'gender',
        'profile_photo',
        'avatar_url',
        'is_admin',
        'is_active',
        'is_online',
        'last_seen_at',
        'mental_health_goals',
        'email_verified_at',
        'phone_verified_at',
        'onwynd_score',
        'streak_count',
        'last_activity_at',
        'timezone',
        'language',
        'preferred_language',
        'country',
        'city',
        'state',
        'address',
        'postal_code',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'medical_conditions',
        'medications',
        'allergies',
        'preferences',
        'subscription_plan_id',
        'trial_ends_at',
        'student_verified_at',
        'student_email',
        'student_id',
        'student_verification_status',
        'onboarding_completed_at',
        'onboarding_step',
        'first_breathing_completed_at',
        'custom_ai_messages',
        'custom_daily_activities',
        'grace_period_days',
        'has_unlimited_quota',
        'quota_override_expires_at',
        'ai_tone_preference',
        'currency',
        'firebase_uid',
        'is_anonymous',
        'has_password',
        'auth_provider',
        'signup_source',
        'signup_utm_medium',
        'signup_utm_campaign',
        'referred_by_ambassador_code',
        'referral_ai_bonus',
        'pending_referral_discount',
        'two_factor_enabled',
        'two_factor_secret',
        'last_login_at',
        'marked_for_deletion',
        'deletion_requested_at',
        'deletion_scheduled_at',
    ];

    protected $appends = ['profile_photo_url'];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'firebase_uid',
        'avatar_url',      // OAuth raw path â€” profile_photo_url accessor is the resolved URL
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'student_verified_at' => 'datetime',
        'mental_health_goals' => 'array',
        'preferences' => 'array',
        'onwynd_score' => 'integer',
        'streak_count' => 'integer',
        'onboarding_step' => 'integer',
        'onboarding_completed_at' => 'datetime',
        'first_breathing_completed_at' => 'datetime',
        'custom_ai_messages' => 'integer',
        'custom_daily_activities' => 'integer',
        'grace_period_days' => 'integer',
        'has_unlimited_quota' => 'boolean',
        'quota_override_expires_at' => 'datetime',
        'ai_tone_preference'         => 'string',
        'referral_ai_bonus'          => 'integer',
        'pending_referral_discount'  => 'decimal:2',
        'has_password'               => 'boolean',
        'two_factor_enabled'         => 'boolean',
        'last_login_at'              => 'datetime',
        'marked_for_deletion'        => 'boolean',
        'deletion_requested_at'      => 'datetime',
        'deletion_scheduled_at'      => 'datetime',
    ];

    public function getLastLoginAttribute()
    {
        return $this->last_login_at ?? $this->last_seen_at;
    }

    /**
     * Get the user's mindfulness activities.
     */
    public function mindfulnessActivities(): HasMany
    {
        return $this->hasMany(MindfulnessActivity::class);
    }

    /**
     * Get the user's journal entries.
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * Get the user's mood entries (backed by MoodLog).
     */
    public function moodEntries(): HasMany
    {
        return $this->hasMany(MoodLog::class);
    }

    /**
     * Alias for mood check-ins to support legacy naming.
     */
    public function moodCheckIns(): HasMany
    {
        return $this->hasMany(MoodLog::class);
    }

    /**
     * Get the user's sleep entries.
     */
    public function sleepEntries(): HasMany
    {
        return $this->hasMany(SleepLog::class);
    }

    /**
     * Get the user's assessments.
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * Get the user's sessions.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    /**
     * Get the user's badges.
     */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'user_badges')
            ->withPivot('earned_at')
            ->withTimestamps();
    }

    /**
     * Get the user's achievements.
     */
    public function achievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    /**
     * Get the user's notifications.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get the user's payments.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the user's subscriptions.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the user's active subscription.
     */
    public function activeSubscription()
    {
        return $this->subscriptions()->where('status', 'active')->first();
    }

    /**
     * Get the user's notification preferences.
     * Used by BaseNotification::via() to determine delivery channels.
     */
    public function notificationSetting(): HasOne
    {
        return $this->hasOne(NotificationSetting::class);
    }

    /**
     * Get the user's roles.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /**
     * Check if the user has a specific role (or any role in an array).
     */
    public function hasRole(string|array $role): bool
    {
        foreach ((array) $role as $r) {
            if (($this->role && $this->role->slug === $r)
                || $this->roles()->where('role', $r)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all roles associated with the user.
     */
    public function allRoles(): array
    {
        $roles = $this->roles()->pluck('role')->toArray();
        if ($this->role && ! in_array($this->role->slug, $roles)) {
            array_unshift($roles, $this->role->slug);
        }

        return array_unique($roles);
    }

    /**
     * Get the user's role.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the user's subscription plan.
     */
    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    /**
     * Get the user's profile.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class);
    }

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(TherapistSpecialty::class, 'therapist_user_specialty', 'user_id', 'specialty_id')
            ->withPivot('years_experience', 'is_primary')
            ->withTimestamps();
    }

    /**
     * Get the user's therapist profile (TherapistProfile model, used by patient-facing routes).
     */
    public function therapistProfile(): HasOne
    {
        return $this->hasOne(TherapistProfile::class);
    }

    /**
     * Get the user's therapist record (Therapist model, used by therapist-facing routes).
     */
    public function therapist(): HasOne
    {
        return $this->hasOne(Therapist::class);
    }

    public function availability(): HasMany
    {
        return $this->hasMany(TherapistAvailability::class, 'therapist_id');
    }

    /**
     * Get the user's availabilities (plural alias for backward compatibility).
     */
    public function availabilities(): HasMany
    {
        return $this->availability();
    }

    /**
     * Get the user's therapist matching preferences.
     */
    public function matchingPreference(): HasOne
    {
        return $this->hasOne(MatchingPreference::class);
    }

    /**
     * Get the user's ambassador record.
     */
    public function ambassador(): HasOne
    {
        return $this->hasOne(Ambassador::class);
    }

    public function streak(): HasOne
    {
        return $this->hasOne(Streak::class);
    }

    /**
     * Get the user's organization memberships.
     */
    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /**
     * Get the user's active organization membership.
     */
    public function activeOrganizationMembership()
    {
        return $this->organizationMemberships()->first();
    }

    public function challengeProgress(): HasMany
    {
        return $this->hasMany(UserChallengeProgress::class);
    }

    /**
     * Get the user's favorite resources.
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(UserFavorite::class);
    }

    /**
     * Get the user's habits.
     */
    public function habits(): HasMany
    {
        return $this->hasMany(Habit::class);
    }

    /**
     * Get the user's crisis safety plan.
     */
    public function safetyPlan()
    {
        return $this->hasOne(CrisisSafetyPlan::class);
    }

    /**
     * Check if the user has a specific granular permission.
     *
     * Checks the `permissions` JSON array on the primary role first,
     * then falls back to the `permissions` table (module-level CRUD flags).
     */
    public function hasPermissionTo(string $permission): bool
    {
        // 1. Check JSON permissions array on the role record
        if ($this->role) {
            $rolePermissions = $this->role->permissions ?? [];
            if (is_array($rolePermissions) && in_array($permission, $rolePermissions)) {
                return true;
            }
        }

        // 2. Check granular permissions table (module:action format, e.g. "users:view")
        [$module, $action] = array_pad(explode(':', $permission, 2), 2, 'view');
        $columnMap = [
            'view'   => 'can_view',
            'create' => 'can_create',
            'edit'   => 'can_edit',
            'delete' => 'can_delete',
        ];
        $column = $columnMap[$action] ?? null;

        if ($column && $this->role_id) {
            $exists = \App\Models\Permission::where('role_id', $this->role_id)
                ->where('module', $module)
                ->where($column, true)
                ->exists();
            if ($exists) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        $activeSubscription = $this->activeSubscription();

        return $activeSubscription && $activeSubscription->current_period_end->isFuture();
    }

    /**
     * Check if user is on trial.
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if user is a verified student.
     */
    public function isStudentVerified(): bool
    {
        return ! is_null($this->student_verified_at) && $this->student_verification_status === 'verified';
    }

    /**
     * Get the user's full name.
     */
    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * H1: Return a fully-qualified public URL for the profile photo.
     * auth/me returns the raw storage path; this accessor converts it
     * to a URL so the frontend never has to guess the storage prefix.
     */
    public function getProfilePhotoUrlAttribute(): ?string
    {
        if (empty($this->profile_photo)) {
            return null;
        }
        // Already an absolute URL (avatar_url from OAuth, or previously resolved path)
        if (filter_var($this->profile_photo, FILTER_VALIDATE_URL)) {
            return $this->profile_photo;
        }

        return Storage::url($this->profile_photo);
    }

    /**
     * Get the user's timezone.
     */
    public function getTimezone(): string
    {
        return $this->timezone ?? 'UTC';
    }

    /**
     * Increment user's streak.
     */
    public function incrementStreak(): void
    {
        $this->increment('streak_count');
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Reset user's streak.
     */
    public function resetStreak(): void
    {
        $this->streak_count = 0;
        $this->save();
    }

    /**
     * Send the email verification notification with a frontend landing link.
     */
    public function sendEmailVerificationNotification(): void
    {
        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $this->getKey(),
                'hash' => sha1($this->getEmailForVerification()),
            ]
        );

        Mail::to($this->email)->send(new ConfirmEmail($signedUrl));
    }
}

