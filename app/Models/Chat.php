<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Chat extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'chats';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'from_user_id',
        'to_user_id',
        'message',
        'message_type',
        'attachments',
        'is_read',
        'read_at',
        'deleted_by',
        'deleted_at_from',
        'deleted_at_to',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'attachments' => 'json',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'deleted_at_from' => 'datetime',
        'deleted_at_to' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the sender of this message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * Get the recipient of this message.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * Scope: Get unread messages for a user.
     */
    public function scopeUnread($query, $userId)
    {
        return $query->where('to_user_id', $userId)
            ->where('is_read', false);
    }

    /**
     * Scope: Get conversation between two users.
     */
    public function scopeConversation($query, $userId1, $userId2)
    {
        return $query->where(function ($q) use ($userId1, $userId2) {
            $q->where('from_user_id', $userId1)->where('to_user_id', $userId2);
        })->orWhere(function ($q) use ($userId1, $userId2) {
            $q->where('from_user_id', $userId2)->where('to_user_id', $userId1);
        });
    }

    /**
     * Mark message as read.
     */
    public function markAsRead(): void
    {
        if (! $this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
    }

    /**
     * Check if message is from user.
     */
    public function isFromUser($userId): bool
    {
        return $this->from_user_id == $userId;
    }

    /**
     * Check if message is to user.
     */
    public function isToUser($userId): bool
    {
        return $this->to_user_id == $userId;
    }
}
