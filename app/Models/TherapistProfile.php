<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapistProfile extends Model
{
    protected $fillable = [
        'user_id',
        'license_number',
        'license_state',
        'license_expiry',
        'specializations',
        'qualifications',
        'languages',
        'experience_years',
        'hourly_rate',
        'currency',
        'bio',
        'video_intro_url',
        'certificate_url',
        'is_verified',
        'verified_at',
        'status',
        'rejection_reason',
        'rejected_at',
        'is_accepting_clients',
        'verification_documents',
        'rating_average',
        'total_sessions',
        'country_of_operation',
        // Founding
        'is_founding',
        'founding_started_at',
        // Location flags
        'account_flagged',
        'flag_reason',
        'flag_note',
        'flagged_at',
    ];

    protected $appends = ['payout_currency'];

    protected $casts = [
        'license_expiry'      => 'date',
        'specializations'     => 'array',
        'qualifications'      => 'array',
        'languages'           => 'array',
        'is_verified'         => 'boolean',
        'verified_at'         => 'datetime',
        'rejected_at'         => 'datetime',
        'is_accepting_clients' => 'boolean',
        'verification_documents' => 'array',
        'rating_average'      => 'decimal:2',
        'is_founding'         => 'boolean',
        'founding_started_at' => 'datetime',
        'account_flagged'     => 'boolean',
        'flagged_at'          => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Derived payout currency based on country of operation.
     * NG -> NGN, everything else -> USD.
     */
    public function getPayoutCurrencyAttribute(): string
    {
        return ($this->country_of_operation ?? '') === 'NG' ? 'NGN' : 'USD';
    }
}
