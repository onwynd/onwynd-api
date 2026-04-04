<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportChatMessage extends Model
{
    protected $fillable = [
        'chat_id',
        'sender_type',
        'sender_id',
        'message',
        'metadata',
        'is_read',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_read' => 'boolean',
    ];

    public function chat()
    {
        return $this->belongsTo(SupportChat::class, 'chat_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** Convenience: was this message written by the AI? */
    public function isFromAi(): bool
    {
        return $this->sender_type === 'ai';
    }
}
