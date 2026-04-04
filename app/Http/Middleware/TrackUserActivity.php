<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $user = $request->user();
            $expiresAt = now()->addMinutes(5);

            // Cache online presence (used for real-time active-user count)
            Cache::put('user-is-online-'.$user->id, true, $expiresAt);

            // Persist last_seen_at to DB at most once per minute to avoid write storms
            $lastSeenKey = 'last-seen-db-'.$user->id;
            if (! Cache::has($lastSeenKey)) {
                $user->updateQuietly(['last_seen_at' => now()]);
                Cache::put($lastSeenKey, true, now()->addMinute());
            }
        }

        return $next($request);
    }
}
