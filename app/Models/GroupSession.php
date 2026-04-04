<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class GroupSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'therapist_id',
        'organization_id',
        'title',
        'description',
        'scheduled_at',
        'duration_minutes',
        'max_participants',
        'current_participants',
        'price_per_seat_kobo',
        'status',
        'session_type',
        'organiser_type',
        'organiser_id',
        'is_recurring',
        'recurrence_rule',
        'parent_session_id',
        'livekit_room_name',
        'livekit_room_token',
        'language',
        'topic_tags',
        'is_org_covered',
        'payment_status',
        'reminder_24h_sent',
        'reminder_1h_sent',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'price_per_seat_kobo' => 'integer',
        'is_recurring' => 'boolean',
        'is_org_covered' => 'boolean',
        'topic_tags' => 'json',
        'reminder_24h_sent' => 'boolean',
        'reminder_1h_sent' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (! $model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
            if (! $model->livekit_room_name) {
                $model->livekit_room_name = 'group_'.$model->uuid;
            }
        });
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_session_participants')
            ->withPivot([
                'guest_email',
                'guest_name',
                'invite_token',
                'invite_status',
                'payment_status',
                'payment_reference',
                'role_in_session',
                'couple_role',
                'joined_at',
            ])
            ->withTimestamps();
    }
}
