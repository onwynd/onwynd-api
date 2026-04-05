<?php

namespace App\Models;

use App\Models\Therapy\TherapistRating;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Therapist extends Model
{
    use HasFactory;

    protected $table = 'therapist_profiles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'status',
        'specializations',
        'experience_years',
        'hourly_rate',
        'has_35min_slot',
        'rate_35min',
        'bio',
        'license_number',
        'license_state',
        'license_expiry',
        'qualifications',
        'languages',
        'currency',
        'video_intro_url',
        'is_verified',
        'verified_at',
        'is_accepting_clients',
        'verification_documents',
        'rating_average',
        'total_sessions',
        // Founding / stipend
        'is_founding',
        'founding_started_at',
        'stipend_eligible',
        'stipend_months_paid',
        // International matching
        'country_of_operation',
        'timezone',
        'payout_currency',
        'cultural_competencies',
        'licensing_country',
        'available_for_international',
        'available_for_nigeria',
        // Stripe Connect
        'stripe_connect_account_id',
        'stripe_connected',
        // Introductory pricing
        'introductory_rate',
        'introductory_sessions_count',
        'introductory_rate_active',
        // Location flag fields
        'account_flagged',
        'flag_reason',
        'flag_note',
        'flagged_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'specializations' => 'array',
        'qualifications' => 'array',
        'languages' => 'array',
        'verification_documents' => 'array',
        'experience_years' => 'integer',
        'hourly_rate' => 'decimal:2',
        'has_35min_slot' => 'boolean',
        'rate_35min' => 'integer',
        'verified_at' => 'datetime',
        'license_expiry' => 'date',
        'is_verified' => 'boolean',
        'is_accepting_clients' => 'boolean',
        'rating_average' => 'decimal:2',
        'is_founding' => 'boolean',
        'founding_started_at' => 'datetime',
        'stipend_eligible' => 'boolean',
        'stipend_months_paid' => 'integer',
        'cultural_competencies' => 'array',
        'available_for_international' => 'boolean',
        'available_for_nigeria' => 'boolean',
        'stripe_connected' => 'boolean',
        // Introductory pricing
        'introductory_rate' => 'decimal:2',
        'introductory_sessions_count' => 'integer',
        'introductory_rate_active' => 'boolean',
        // Location flag fields
        'account_flagged' => 'boolean',
        'flagged_at' => 'datetime',
    ];

    /**
     * Get the user that owns the therapist profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): HasMany
    {
        return $this->hasMany(TherapistAvailability::class, 'therapist_id', 'user_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(TherapistRating::class, 'therapist_id', 'user_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TherapySession::class, 'therapist_id', 'user_id');
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(Patient::class, 'patient_favorites', 'therapist_id', 'patient_id')->withTimestamps();
    }

    /**
     * Return the effective session rate for a given patient user ID.
     *
     * If introductory pricing is active and the patient has not yet exhausted
     * the introductory session allotment, returns the discounted rate; otherwise
     * returns the standard rate.
     *
     * @param  int|null  $userId  Patient's user ID (users.id)
     */
    public function getEffectiveRateForUser(?int $userId = null): array
    {
        $currency = $this->payout_currency ?? 'NGN';
        $symbol = $currency === 'USD' ? '$' : '₦';
        $standardRate = (float) $this->hourly_rate;

        if (! $this->introductory_rate_active || ! $this->introductory_rate || ! $userId) {
            return ['rate' => $standardRate, 'is_introductory' => false, 'currency' => $currency, 'symbol' => $symbol];
        }

        // Count completed sessions this patient has had with this therapist
        $sessionCount = \App\Models\TherapySession::where('patient_id', $userId)
            ->where('therapist_id', $this->user_id)
            ->where('status', 'completed')
            ->count();

        if ($sessionCount < ($this->introductory_sessions_count ?? 0)) {
            return [
                'rate' => (float) $this->introductory_rate,
                'is_introductory' => true,
                'sessions_remaining' => ($this->introductory_sessions_count - $sessionCount),
                'currency' => $currency,
                'symbol' => $symbol,
            ];
        }

        return ['rate' => $standardRate, 'is_introductory' => false, 'currency' => $currency, 'symbol' => $symbol];
    }
}
