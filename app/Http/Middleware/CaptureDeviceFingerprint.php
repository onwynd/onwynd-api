<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CaptureDeviceFingerprint
{
    /**
     * Capture the X-Device-Fingerprint header sent by the frontend (FingerprintJS).
     * Stores the fingerprint alongside the authenticated user for security/fraud-detection.
     * The fingerprint is cached per-user and used for:
     *   - Device continuity checks (detect stolen tokens from unknown devices)
     *   - Fraud detection (flag sudden device changes)
     *   - Session analytics
     */
    public function handle(Request $request, Closure $next): Response
    {
        $fingerprint = $request->header('X-Device-Fingerprint');

        if ($fingerprint && $request->user()) {
            $user = $request->user();

            // Make fingerprint available on the request for downstream use
            $request->attributes->set('device_fingerprint', $fingerprint);

            // Store the fingerprint on the user's known-devices list (cached, updated at most once per minute)
            $cacheKey = "device_fingerprint_{$user->id}";
            $knownFingerprints = Cache::get($cacheKey, []);

            if (! in_array($fingerprint, $knownFingerprints, true)) {
                $knownFingerprints[] = $fingerprint;
                // Keep only the last 5 known devices
                if (count($knownFingerprints) > 5) {
                    $knownFingerprints = array_slice($knownFingerprints, -5);
                }
                Cache::put($cacheKey, $knownFingerprints, now()->addDays(30));
            }
        }

        return $next($request);
    }
}
