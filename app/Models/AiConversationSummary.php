<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiConversationSummary extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'summary',
        'message_count',
        'last_message_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
