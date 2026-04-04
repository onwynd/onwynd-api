<?php

namespace App\Models\AI;

use App\Enums\RiskLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIDiagnostic extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'session_id',
        'status',
        'current_stage',
        'risk_level',
        'risk_score',
        'summary',
        'recommended_actions',
    ];

    protected $casts = [
        'summary' => 'array',
        'recommended_actions' => 'array',
        'risk_level' => RiskLevel::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversations()
    {
        return $this->hasMany(AIConversation::class)->orderBy('created_at', 'asc');
    }
}
