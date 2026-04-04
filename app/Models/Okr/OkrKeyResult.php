<?php

namespace App\Models\Okr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OkrKeyResult extends Model
{
    protected $table = 'okr_key_results';

    protected $fillable = [
        'objective_id', 'title', 'description',
        'metric_key', 'metric_type', 'unit',
        'start_value', 'current_value', 'target_value',
        'owner_id', 'due_date', 'health_status', 'last_refreshed_at',
    ];

    protected $casts = [
        'start_value'       => 'float',
        'current_value'     => 'float',
        'target_value'      => 'float',
        'due_date'          => 'date',
        'last_refreshed_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function objective(): BelongsTo
    {
        return $this->belongsTo(OkrObjective::class, 'objective_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(OkrCheckIn::class, 'key_result_id')
                    ->orderBy('recorded_at', 'desc');
    }

    public function initiatives(): HasMany
    {
        return $this->hasMany(OkrInitiative::class, 'key_result_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(OkrAlert::class, 'key_result_id');
    }

    // ── Computed attributes ───────────────────────────────────────────────────

    /**
     * Progress (%): (current - start) / (target - start) × 100.
     * Guarded against division by zero.
     * Allows values above 100 for exceeded targets.
     */
    public function getProgressAttribute(): float
    {
        $range = $this->target_value - $this->start_value;
        if (abs($range) < 0.0001) return 0.0;

        $progress = (($this->current_value - $this->start_value) / $range) * 100;
        return round(max(0, $progress), 2);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeAuto($query)
    {
        return $query->where('metric_type', 'auto');
    }

    public function scopeAtRiskOrOffTrack($query)
    {
        return $query->whereIn('health_status', ['at_risk', 'off_track']);
    }
}
