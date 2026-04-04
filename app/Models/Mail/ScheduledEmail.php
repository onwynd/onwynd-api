<?php

namespace App\Models\Mail;

use App\Models\User;
use App\Traits\HasUUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduledEmail extends Model
{
    use HasUUID, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'scheduled_emails';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'recipient_email',
        'recipient_name',
        'email_type',
        'data',
        'scheduled_at',
        'sent_at',
        'status',
        'attempts',
        'last_attempted_at',
        'last_error',
        'is_template',
        'template_name',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'data' => 'json',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'last_attempted_at' => 'datetime',
        'is_template' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user associated with the email.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if email is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if email was sent
     */
    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * Check if email failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if email can be retried
     */
    public function canRetry(): bool
    {
        return $this->status === 'failed' && $this->attempts < 3;
    }

    /**
     * Check if email is ready to send
     */
    public function isReadyToSend(): bool
    {
        return $this->status === 'pending' && $this->scheduled_at <= now();
    }

    /**
     * Mark email as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark email as failed
     */
    public function markAsFailed(?string $error = null): void
    {
        $this->update([
            'status' => 'failed',
            'last_attempted_at' => now(),
            'last_error' => $error,
            'attempts' => $this->attempts + 1,
        ]);
    }

    /**
     * Scope: Only pending emails
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Only sent emails
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope: Only failed emails
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Emails ready to send
     */
    public function scopeReadyToSend($query)
    {
        return $query->where('status', 'pending')
            ->where('scheduled_at', '<=', now());
    }

    /**
     * Scope: Failed emails that can be retried
     */
    public function scopeRetryable($query)
    {
        return $query->where('status', 'failed')
            ->where('attempts', '<', 3)
            ->where('last_attempted_at', '<=', now()->subHours(1));
    }

    /**
     * Scope: By email type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('email_type', $type);
    }

    /**
     * Scope: By recipient email
     */
    public function scopeByRecipient($query, string $email)
    {
        return $query->where('recipient_email', $email);
    }
}
