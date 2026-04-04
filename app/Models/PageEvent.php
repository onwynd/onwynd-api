<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageEvent extends Model
{
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'session_id', 'user_id', 'ip', 'country', 'city', 'page', 'referrer',
        'utm_source', 'utm_medium', 'utm_campaign', 'user_agent',
        'scroll_pct', 'duration_ms', 'visitor_type', 'quality',
        'event_type', 'meta', 'created_at',
    ];

    protected $casts = [
        'meta'       => 'array',
        'created_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }

    /** Simple bot detection heuristic from user agent. */
    public static function detectQuality(string $ua): string
    {
        $botPatterns = '/bot|crawl|spider|slurp|search|archive|fetch|feed|ping|scan|check|http|python|php|java|curl|wget|axios|okhttp|scrapy|headless|phantom|puppet|selenium|playwright|lighthouse/i';
        if (preg_match($botPatterns, $ua)) {
            return 'bot';
        }
        // Very short or empty UA → suspicious
        if (strlen($ua) < 20) {
            return 'suspicious';
        }
        return 'human';
    }
}
