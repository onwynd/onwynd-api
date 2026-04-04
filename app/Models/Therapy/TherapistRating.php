<?php

namespace App\Models\Therapy;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TherapistRating extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'therapist_id',
        'patient_id',
        'session_id',
        'rating',
        'nps_score',
        'feedback',
        'is_anonymous',
    ];

    public function therapist()
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }
}
