<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TherapySession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'patient_id',
        'therapist_id',
        'session_type',
        'status',
        'scheduled_at',
        'started_at',
        'ended_at',
        'checkin_sent_at',
        'duration_minutes',
        'session_rate',
        'payment_status',
        'payment_method',
        'commission_amount',
        'commission_percentage',
        'is_paid_out',
        'booking_notes',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'meeting_url',
        'room_id',
        'recording_url',
        'is_anonymous',
        'anonymous_fingerprint',
        'anonymous_nickname',
        'rating',
        'review_text',
        'reviewed_at',
        'promo_code_id',
        'promo_discount_amount',
        'booking_fee_amount',
        'booking_fee_waived',
        'booking_fee_waiver_reason',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'checkin_sent_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'session_rate'          => 'decimal:2',
        'is_anonymous'          => 'boolean',
        'reviewed_at'           => 'datetime',
        'promo_discount_amount' => 'decimal:2',
        'booking_fee_amount'    => 'decimal:2',
        'booking_fee_waived'    => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        static::deleting(function (TherapySession $session) {
            // Cascade soft-delete to session notes
            if ($session->sessionNote()->exists()) {
                $session->sessionNote()->delete();
            }
            // Payments are financial records — do not delete them
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function sessionNote(): HasOne
    {
        return $this->hasOne(SessionNote::class, 'session_id');
    }

    public function participants()
    {
        return $this->hasMany(SessionParticipant::class, 'session_id');
    }

    public function attendees()
    {
        return $this->belongsToMany(User::class, 'session_participants', 'session_id', 'user_id')
            ->withPivot('role', 'status', 'joined_at', 'left_at')
            ->withTimestamps();
    }

    public function promotionalCode(): BelongsTo
    {
        return $this->belongsTo(PromotionalCode::class, 'promo_code_id');
    }
}
