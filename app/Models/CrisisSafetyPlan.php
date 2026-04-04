<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrisisSafetyPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'warning_signs',
        'coping_strategies',
        'support_contacts',
        'professional_contacts',
        'safe_environment_steps',
        'reasons_for_living',
    ];

    protected $casts = [
        'warning_signs' => 'array',
        'coping_strategies' => 'array',
        'support_contacts' => 'array',
        'professional_contacts' => 'array',
        'safe_environment_steps' => 'array',
        'reasons_for_living' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
