<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StressAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'stress_level',
        'stressors',
        'symptoms',
        'notes',
        'facial_image_url',
        'coping_mechanisms',
        'ai_insights',
    ];

    protected $casts = [
        'stressors' => 'array',
        'symptoms' => 'array',
        'coping_mechanisms' => 'array',
        'ai_insights' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
