<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department',
        'status',
        'medical_history',
        'emergency_contact',
        'insurance_provider',
        'insurance_policy_number',
        'preferences',
    ];

    protected $casts = [
        'medical_history' => 'array',
        'emergency_contact' => 'array',
        'preferences' => 'array',
        'status' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moodLogs(): HasMany
    {
        return $this->hasMany(MoodLog::class);
    }

    public function aiChats(): HasMany
    {
        return $this->hasMany(AIChat::class);
    }

    public function favorites()
    {
        return $this->belongsToMany(Therapist::class, 'patient_favorites', 'patient_id', 'therapist_id')->withTimestamps();
    }
}
