<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $table = 'appointments';

    protected $fillable = [
        'uuid',
        'patient_id',
        'therapist_id',
        'session_type',
        'status',
        'scheduled_at',
        'duration_minutes',
        'session_rate',
        'payment_status',
        'booking_notes',
    ];
}
