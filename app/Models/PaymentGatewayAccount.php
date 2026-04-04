<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PaymentGatewayAccount Model
 *
 * Represents gateway accounts for users/therapists
 */
class PaymentGatewayAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payment_gateway_accounts';

    protected $fillable = [
        'uuid',
        'user_id',
        'therapist_id',
        'gateway',
        'gateway_account_id',
        'email',
        'account_number',
        'bank_code',
        'account_name',
        'account_type',
        'bvn',
        'is_verified',
        'verified_at',
        'verification_details',
        'status',
        'is_primary',
        'metadata',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_primary' => 'boolean',
        'verified_at' => 'datetime',
        'verification_details' => 'json',
        'metadata' => 'json',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(Therapist::class);
    }

    public function scopeByGateway($query, $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function markAsVerified(): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
            'status' => 'active',
        ]);
    }

    public function markAsUnverified(): void
    {
        $this->update([
            'is_verified' => false,
            'verified_at' => null,
            'status' => 'inactive',
        ]);
    }

    public function setPrimary(): void
    {
        // Unset other primary accounts for this owner
        if ($this->user_id) {
            $this->user->gatewayAccounts()
                ->where('id', '!=', $this->id)
                ->update(['is_primary' => false]);
        }

        if ($this->therapist_id) {
            $this->therapist->paymentAccounts()
                ->where('id', '!=', $this->id)
                ->update(['is_primary' => false]);
        }

        $this->update(['is_primary' => true]);
    }

    public function getMaskedAccountAttribute(): string
    {
        if (! $this->account_number) {
            return '****';
        }

        $last4 = substr($this->account_number, -4);

        return '**** **** **** '.$last4;
    }
}
