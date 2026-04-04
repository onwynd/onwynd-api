<?php

namespace App\Models\Assessment;

use App\Models\Assessment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentResponse extends Model
{
    use HasFactory;

    protected $table = 'assessment_responses';

    protected $fillable = [
        'assessment_id',
        'question_id',
        'response_value',
        'response_type',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
            'response_value' => 'json',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
