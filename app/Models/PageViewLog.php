<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageViewLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'page_key', 'record_type', 'record_id',
        'ip_address', 'user_agent', 'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Polymorphic subject (e.g. Invoice, Payroll)
    public function subject()
    {
        return $this->morphTo('record');
    }

    // ── Helper ─────────────────────────────────────────────────────────────────

    /**
     * Record a page view for the authenticated user.
     */
    public static function record(
        int $userId,
        string $pageKey,
        ?string $recordType = null,
        ?int $recordId = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): void {
        static::create([
            'user_id'     => $userId,
            'page_key'    => $pageKey,
            'record_type' => $recordType,
            'record_id'   => $recordId,
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
            'viewed_at'   => now(),
        ]);
    }
}
