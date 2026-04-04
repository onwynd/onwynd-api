<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PaymentRefund Model
 *
 * Represents refund transactions
 */
class PaymentRefund extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payment_refunds';

    protected $fillable = [
        'uuid',
        'payment_id',
        'user_id',
        'amount',
        'reason',
        'notes',
        'status',
        'gateway_refund_id',
        'initiated_at',
        'processed_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'initiated_at' => 'datetime',
        'processed_at' => 'datetime',
        'metadata' => 'json',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function markAsProcessed(): void
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
            'processed_at' => now(),
        ]);
    }
}
