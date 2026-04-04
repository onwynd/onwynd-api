<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conversation Model
 *
 * Represents a conversation between users
 */
class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_group',
        'created_by',
        'last_message_at',
    ];

    protected $casts = [
        'is_group' => 'boolean',
        'last_message_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the participants in this conversation
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants', 'conversation_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Get the messages in this conversation
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Assessments attached to this conversation (AI/chat context)
     */
    public function assessments(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\UserAssessmentResult::class, 'conversation_assessments', 'conversation_id', 'assessment_result_id')
            ->withTimestamps();
    }

    /**
     * Check if a user is participant of this conversation
     */
    public function hasParticipant(int $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    /**
     * Add a participant to the conversation
     */
    public function addParticipant(int $userId): void
    {
        if (! $this->hasParticipant($userId)) {
            $this->participants()->attach($userId);
        }
    }

    /**
     * Remove a participant from the conversation
     */
    public function removeParticipant(int $userId): void
    {
        $this->participants()->detach($userId);
    }
}
