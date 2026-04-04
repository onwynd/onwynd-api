<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapistAvailability extends Model
{
    protected $fillable = [
        'therapist_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_recurring',
        'specific_date',
        'is_available',
    ];

    protected $casts = [
        'is_recurring' => 'boolean',
        'specific_date' => 'date',
        'is_available' => 'boolean',
    ];

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }
}
