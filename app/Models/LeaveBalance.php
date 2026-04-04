<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    protected $fillable = [
        'user_id',
        'year',
        'vacation_days',
        'sick_days',
        'personal_days',
        'used_vacation',
        'used_sick',
        'used_personal',
    ];

    protected $casts = [
        'vacation_days' => 'decimal:2',
        'sick_days' => 'decimal:2',
        'personal_days' => 'decimal:2',
        'used_vacation' => 'decimal:2',
        'used_sick' => 'decimal:2',
        'used_personal' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
