<?php

namespace App\Models\AI;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIConversation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ai_diagnostic_id',
        'role',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function diagnostic()
    {
        return $this->belongsTo(AIDiagnostic::class, 'ai_diagnostic_id');
    }
}
