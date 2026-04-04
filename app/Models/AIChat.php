<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIChat extends Model
{
    use HasFactory;

    protected $table = 'ai_chats';

    protected $fillable = [
        'user_id',
        'session_id', // Group messages by session/conversation
        'message',
        'sender', // 'user' or 'ai'
        'sentiment_score',
        'risk_level',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
