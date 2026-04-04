<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChatRequest extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'chat_requests';

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
        'status',
        'responded_at',
    ];

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_BLOCKED = 'blocked';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'responded_at' => 'datetime',
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
            if (empty($model->status)) {
                $model->status = self::STATUS_PENDING;
            }
        });
    }

    /**
     * Get the user who sent the request.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * Get the user who received the request.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * Scope: Get pending requests for a user.
     */
    public function scopePending($query, $userId)
    {
        return $query->where('to_user_id', $userId)
            ->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: Get accepted chat requests.
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    /**
     * Accept the chat request.
     */
    public function accept(): bool
    {
        return $this->update([
            'status' => self::STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);
    }

    /**
     * Reject the chat request.
     */
    public function reject(): bool
    {
        return $this->update([
            'status' => self::STATUS_REJECTED,
            'responded_at' => now(),
        ]);
    }

    /**
     * Block the chat request.
     */
    public function block(): bool
    {
        return $this->update([
            'status' => self::STATUS_BLOCKED,
            'responded_at' => now(),
        ]);
    }

    /**
     * Check if request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if request is accepted.
     */
    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }
}
