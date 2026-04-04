<?php

namespace App\Http\Controllers\API\V1;

use App\Models\PageEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Public endpoint — receives frontend analytics beacons.
 * Intentionally lightweight: no auth required, respond immediately.
 */
class AnalyticsBeaconController
{
    public function track(Request $request)
    {
        $data = $request->validate([
            'session_id'   => 'required|string|max:64',
            'page'         => 'required|string|max:2048',
            'referrer'     => 'nullable|string|max:2048',
            'utm_source'   => 'nullable|string|max:191',
            'utm_medium'   => 'nullable|string|max:191',
            'utm_campaign' => 'nullable|string|max:191',
            'scroll_pct'   => 'nullable|integer|min:0|max:100',
            'duration_ms'  => 'nullable|integer|min:0',
            'event_type'   => 'nullable|in:pageview,click,scroll',
            'meta'         => 'nullable|array',
        ]);

        $ip = $request->ip();
        $ua = $request->userAgent() ?? '';

        // Determine visitor type via session cache
        $sessionKey = 'visitor_' . $data['session_id'];
        $visitorType = Cache::has($sessionKey) ? 'returning' : 'new';
        Cache::put($sessionKey, true, now()->addDays(30));

        PageEvent::create([
            'session_id'   => $data['session_id'],
            'user_id'      => $request->user()?->id,
            'ip'           => $ip,
            'page'         => $data['page'],
            'referrer'     => $data['referrer'] ?? null,
            'utm_source'   => $data['utm_source'] ?? null,
            'utm_medium'   => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'user_agent'   => substr($ua, 0, 512),
            'scroll_pct'   => $data['scroll_pct'] ?? 0,
            'duration_ms'  => $data['duration_ms'] ?? 0,
            'event_type'   => $data['event_type'] ?? 'pageview',
            'visitor_type' => $visitorType,
            'quality'      => PageEvent::detectQuality($ua),
            'meta'         => $data['meta'] ?? null,
        ]);

        return response()->json(['ok' => true], 200);
    }
}
