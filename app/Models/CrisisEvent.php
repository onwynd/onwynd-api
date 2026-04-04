<?php

namespace App\Models;

use App\Models\Institutional\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrisisEvent extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'org_id',
        'session_id',
        'risk_level',
        'triggered_at',
        'resources_shown',
        'banner_shown',
        'override_active',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'triggered_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'resources_shown' => 'boolean',
        'banner_shown' => 'boolean',
        'override_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
