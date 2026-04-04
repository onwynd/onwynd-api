<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SupportChat extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'session_token',
        'status',
        'assigned_agent_id',
        'handover_requested_at',
        'handover_at',
        'first_response_at',
        'closed_at',
        'user_context',
        'wyndchat_conversation_id',
    ];

    protected $casts = [
        'user_context' => 'array',
        'handover_requested_at' => 'datetime',
        'handover_at' => 'datetime',
        'first_response_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function messages()
    {
        return $this->hasMany(SupportChatMessage::class, 'chat_id')->orderBy('created_at');
    }

    public function latestMessage()
    {
        return $this->hasOne(SupportChatMessage::class, 'chat_id')->latestOfMany();
    }

    /** Is the AI bot still the responder? */
    public function isAiMode(): bool
    {
        return $this->status === 'ai';
    }

    /** Has a human agent claimed this chat? */
    public function isHumanMode(): bool
    {
        return $this->status === 'human';
    }
}
