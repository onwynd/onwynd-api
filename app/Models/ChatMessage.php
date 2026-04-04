<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_type',
        'message_text',
        'attachments',
        'sentiment_analysis',
        'ai_confidence',
    ];

    protected $casts = [
        'attachments' => 'array',
        'sentiment_analysis' => 'array',
        'ai_confidence' => 'decimal:2',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }
}
