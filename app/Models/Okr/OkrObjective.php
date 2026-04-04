<?php

namespace App\Models\Okr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class OkrObjective extends Model
{
    protected $table = 'okr_objectives';

    protected $fillable = [
        'title', 'description', 'owner_id', 'quarter', 'status', 'parent_id', 'department',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Parent objective (team objectives point up to company objective). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(OkrObjective::class, 'parent_id');
    }

    /** Child objectives (company → team). */
    public function children(): HasMany
    {
        return $this->hasMany(OkrObjective::class, 'parent_id');
    }

    public function keyResults(): HasMany
    {
        return $this->hasMany(OkrKeyResult::class, 'objective_id');
    }

    // ── Computed attributes ───────────────────────────────────────────────────

    /**
     * Overall health: worst-case across all KRs.
     * off_track > at_risk > on_track.
     */
    public function getHealthAttribute(): string
    {
        $statuses = $this->keyResults->pluck('health_status');
        if ($statuses->contains('off_track')) return 'off_track';
        if ($statuses->contains('at_risk'))   return 'at_risk';
        return 'on_track';
    }

    /**
     * Average progress (%) across all key results.
     */
    public function getProgressAttribute(): float
    {
        $krs = $this->keyResults;
        if ($krs->isEmpty()) return 0.0;
        return round($krs->avg(fn ($kr) => $kr->progress), 1);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForQuarter($query, string $quarter)
    {
        return $query->where('quarter', $quarter);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }
}
