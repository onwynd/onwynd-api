<?php

namespace App\Models\Therapy;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchingPreference extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'therapist_preferences';

    protected $fillable = [
        'user_id',
        'specialties',
        'gender_preference',
        'languages',
        'communication_style',
        'availability_slots',
        'min_experience_years',
        'max_hourly_rate',
        'timezone',
    ];

    protected $casts = [
        'specialties' => 'array',
        'languages' => 'array',
        'availability_slots' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
