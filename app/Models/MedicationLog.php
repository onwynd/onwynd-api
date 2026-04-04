<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'prescription_id',
        'medication_name',
        'dosage_taken',
        'taken_at',
        'notes',
        'mood_rating',
        'skipped',
        'skip_reason',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
        'skipped' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
