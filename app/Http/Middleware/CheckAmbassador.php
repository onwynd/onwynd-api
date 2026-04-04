<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAmbassador
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Debug: Log the user and ambassador status
        \Log::info('CheckAmbassador middleware', [
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->first_name.' '.$request->user()->last_name,
            'has_ambassador' => $request->user()->ambassador ? 'yes' : 'no',
            'ambassador_status' => $request->user()->ambassador ? $request->user()->ambassador->status : 'none',
        ]);

        // Check if user has an active ambassador profile
        $ambassador = $request->user()->ambassador;
        if (! $ambassador || $ambassador->status !== 'active') {
            return response()->json([
                'message' => 'Unauthorized. You must be an active ambassador to access this resource.',
                'debug' => [
                    'has_ambassador' => $request->user()->ambassador ? 'yes' : 'no',
                    'ambassador_status' => $request->user()->ambassador ? $request->user()->ambassador->status : 'none',
                ],
            ], 403);
        }

        return $next($request);
    }
}
