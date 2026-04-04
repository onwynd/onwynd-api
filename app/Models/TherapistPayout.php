<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TherapistPayout Model
 *
 * Represents payments made to therapists
 */
class TherapistPayout extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'therapist_payouts';

    protected $fillable = [
        'uuid',
        'therapist_id',
        'amount',
        'currency',
        'payment_reason',
        'payment_gateway',
        'payment_reference',
        'gateway_payment_id',
        'status',
        'initiated_at',
        'completed_at',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'json',
    ];

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(Therapist::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByTherapist($query, $therapistId)
    {
        return $query->where('therapist_id', $therapistId);
    }

    public function scopeByReason($query, $reason)
    {
        return $query->where('payment_reason', $reason);
    }

    public function getFormattedAmountAttribute(): string
    {
        $symbols = [
            'NGN' => '₦',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
        ];

        $symbol = $symbols[$this->currency] ?? $this->currency;

        return $symbol.number_format($this->amount, 2);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed($reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);
    }
}
