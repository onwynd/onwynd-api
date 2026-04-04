<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Habit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'frequency',
        'target_count',
        'reminder_times',
        'start_date',
        'end_date',
        'category',
        'streak',
        'longest_streak',
        'is_archived',
    ];

    protected $casts = [
        'reminder_times' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_archived' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function logs()
    {
        return $this->hasMany(HabitLog::class);
    }
}
