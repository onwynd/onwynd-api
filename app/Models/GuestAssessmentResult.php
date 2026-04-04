<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestAssessmentResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'guest_token',
        'answers',
        'total_score',
        'percentage',
        'severity_level',
        'interpretation',
        'recommendations',
        'linked_user_id',
        'completed_at',
    ];

    protected $casts = [
        'answers' => 'array',
        'recommendations' => 'array',
        'completed_at' => 'datetime',
        'linked_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function linkedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }

    /**
     * Link this guest assessment result to a user account
     */
    public function linkToUser(User $user): UserAssessmentResult
    {
        // Create user assessment result from guest result
        $userResult = UserAssessmentResult::create([
            'user_id' => $user->id,
            'assessment_id' => $this->assessment_id,
            'answers' => $this->answers,
            'total_score' => $this->total_score,
            'percentage' => $this->percentage,
            'severity_level' => $this->severity_level,
            'interpretation' => $this->interpretation,
            'recommendations' => $this->recommendations,
            'completed_at' => $this->completed_at,
        ]);

        // Mark guest result as linked
        $this->update([
            'linked_user_id' => $user->id,
            'linked_at' => now(),
        ]);

        return $userResult;
    }

    /**
     * Check if this guest assessment result has been linked to a user
     */
    public function isLinked(): bool
    {
        return ! is_null($this->linked_user_id);
    }
}
