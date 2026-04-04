<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HabitLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'habit_id',
        'date',
        'count',
        'status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function habit()
    {
        return $this->belongsTo(Habit::class);
    }
}
