<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentQuestion extends Model
{
    protected $fillable = [
        'assessment_id',
        'question_text',
        'question_type',
        'options',
        'scale_min',
        'scale_max',
        'scale_labels',
        'order_number',
        'is_required',
    ];

    protected $casts = [
        'options' => 'array',
        'scale_labels' => 'array',
        'is_required' => 'boolean',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
