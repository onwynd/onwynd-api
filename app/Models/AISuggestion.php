<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AISuggestion extends Model
{
    use HasFactory;

    protected $table = 'ai_suggestions';

    protected $fillable = [
        'user_id',
        'type', // mood, sleep, journal, general
        'content',
        'actionable_items',
        'is_dismissed',
        'generated_at',
    ];

    protected $casts = [
        'actionable_items' => 'array',
        'is_dismissed' => 'boolean',
        'generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
