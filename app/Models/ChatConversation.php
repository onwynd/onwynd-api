<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChatConversation extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'ai_personality',
        'context_data',
        'is_archived',
        'last_message_at',
    ];

    protected $casts = [
        'context_data' => 'array',
        'is_archived' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    /**
     * Assessments attached to this chat conversation (used as AI context)
     */
    public function assessments(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\UserAssessmentResult::class, 'conversation_assessments', 'conversation_id', 'assessment_result_id')
            ->withTimestamps();
    }
}
