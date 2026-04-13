<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Payment Model
 *
 * Represents a payment transaction in the system
 * Supports multiple currencies and payment gateways
 */
class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'session_id',
        'subscription_id',
        'organization_member_id',
        'amount',
        'currency',
        'payment_type',
        'description',
        'payment_gateway',
        'payment_reference',
        'gateway_payment_id',
        'authorization_url',
        'access_code',
        'status',
        'payment_status',
        'initiated_at',
        'completed_at',
        'failed_at',
        'failure_reason',
        'refund_amount',
        'refunded_at',
        'gateway_response',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'gateway_response' => 'json',
        'metadata' => 'json',
    ];

    protected $appends = ['is_paid', 'status_display'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    /**
     * Relations
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TherapySession::class, 'session_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function organizationMember(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Institutional\OrganizationMember::class, 'organization_member_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    /**
     * Query Scopes
     */
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

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopeByGateway($query, $gateway)
    {
        return $query->where('payment_gateway', $gateway);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('payment_type', $type);
    }

    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', strtoupper($currency));
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }

    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('initiated_at', [$startDate, $endDate]);
    }

    /**
     * Accessors & Mutators
     */
    public function getIsPaidAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getStatusDisplayAttribute(): string
    {
        $displays = [
            'draft' => 'Draft',
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Paid',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
        ];

        return $displays[$this->status] ?? ucfirst($this->status);
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

    /**
     * Methods
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'payment_status' => 'paid',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed($reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'payment_status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);
    }

    public function refund($amount = null): void
    {
        $refundAmount = $amount ?? $this->amount;

        $this->update([
            'refund_amount' => $refundAmount,
            'refunded_at' => now(),
        ]);

        if ($refundAmount >= $this->amount) {
            $this->update(['status' => 'refunded']);
        }
    }

    public function isRefundable(): bool
    {
        return $this->status === 'completed' && $this->refund_amount < $this->amount;
    }

    public function getTotalRefunded(): float
    {
        return $this->refunds()->where('status', 'completed')->sum('amount');
    }

    public function getNetAmount(): float
    {
        return $this->amount - $this->getTotalRefunded();
    }
}
