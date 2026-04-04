<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserAssessmentResult extends Model
{
    protected $fillable = [
        'user_id',
        'assessment_id',
        'answers',
        'total_score',
        'interpretation',
        'severity_level',
        'recommendations',
        'is_shared_with_therapist',
        'shared_with_therapist_id',
        'completed_at',
    ];

    protected $casts = [
        'answers' => 'array',
        'recommendations' => 'array',
        'is_shared_with_therapist' => 'boolean',
        'completed_at' => 'datetime',
        'total_score' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function sharedWithTherapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_therapist_id');
    }

    /**
     * Conversations that reference this assessment result
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_assessments', 'assessment_result_id', 'conversation_id')
            ->withTimestamps();
    }
}
