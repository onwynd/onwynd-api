<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $featureName): Response
    {
        // Cache the setting to avoid hitting DB on every request
        $isEnabled = Cache::remember("feature_{$featureName}_enabled", 60, function () use ($featureName) {
            $setting = Setting::where('key', "feature_{$featureName}_enabled")->first();

            // Default to enabled if setting doesn't exist, to avoid breaking changes before seeding
            return $setting ? filter_var($setting->value, FILTER_VALIDATE_BOOLEAN) : true;
        });

        if (! $isEnabled) {
            return response()->json([
                'success' => false,
                'message' => "The {$featureName} feature is currently disabled by the administrator.",
            ], 403);
        }

        return $next($request);
    }
}
