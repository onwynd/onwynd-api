<?php

namespace App\Models\Therapy;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TherapistSpecialty extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'category', 'description'];

    public function therapists()
    {
        return $this->belongsToMany(User::class, 'therapist_user_specialty', 'specialty_id', 'user_id')
            ->withPivot('years_experience', 'is_primary')
            ->withTimestamps();
    }
}
