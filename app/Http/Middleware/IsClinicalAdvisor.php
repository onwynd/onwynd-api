<?php

namespace App\Http\Middleware;

use App\Models\ClinicalAdvisor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsClinicalAdvisor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if user has the role (assuming a role system exists, e.g. Spatie or simple column)
        // Adjust this check based on actual Role implementation.
        // For now, assuming user has 'role_id' or 'role' attribute or relationship.
        // User migration shows 'role_id'. Assuming 2 or specific ID is clinical advisor,
        // OR user has a method hasRole.

        // If strict role check is needed via relationship:
        // if (!$user->role || $user->role->name !== 'clinical_advisor') ...

        // But we can also check if they have a ClinicalAdvisor record.
        $advisor = ClinicalAdvisor::where('user_id', $user->id)->first();

        if (! $advisor) {
            return response()->json(['message' => 'Unauthorized: Clinical Advisor profile not found.'], 403);
        }

        if ($advisor->verification_status !== 'verified') {
            return response()->json(['message' => 'Unauthorized: Clinical Advisor account is not verified.'], 403);
        }

        if ($advisor->status !== 'active') {
            return response()->json(['message' => 'Unauthorized: Clinical Advisor account is not active.'], 403);
        }

        // Attach advisor to request for easy access
        $request->merge(['clinical_advisor' => $advisor]);

        return $next($request);
    }
}
