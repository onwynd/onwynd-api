<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaitlistSubmission extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'role',
        'country',
        'referral_source',
        'message',
        'status',
        'invited_at',
        'years_of_experience',
        'specialty',
        'institution_type',
        'organization_name',
        'company_size',
        'student_count',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
    ];

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
