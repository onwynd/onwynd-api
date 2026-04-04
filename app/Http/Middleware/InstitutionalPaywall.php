<?php

namespace App\Http\Middleware;

use App\Models\QuotaSetting;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks institutional users when their subscription has expired beyond the grace period.
 * Applied to institutional routes that require active subscription.
 */
class InstitutionalPaywall
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Only applies to institutional role
        if ($user->role?->slug !== 'institutional') {
            return $next($request);
        }

        // Get first organization the user belongs to
        $organization = $user->organization ?? null;

        if (! $organization) {
            return $next($request);
        }

        // If paywall is explicitly activated
        if ($organization->paywall_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your institutional subscription is inactive. Please renew to continue.',
                'code' => 'PAYWALL_ACTIVE',
            ], 402);
        }

        // Check expiry + grace period
        if ($organization->subscription_expires_at) {
            $expired = Carbon::parse($organization->subscription_expires_at);
            $systemDefault = (int) (QuotaSetting::query()->orderBy('id')->value('corporate_grace_period_days') ?? 14);
            $graceDays = (int) ($organization->grace_period_days ?? $systemDefault);
            $hardExpiry = $expired->copy()->addDays($graceDays);

            if (Carbon::now()->greaterThan($hardExpiry)) {
                // Auto-activate paywall
                $organization->update(['paywall_active' => true]);

                return response()->json([
                    'success' => false,
                    'message' => 'Your subscription expired on '.$expired->format('M d, Y').'. The '.$graceDays.'-day grace period has ended. Please renew to continue.',
                    'code' => 'SUBSCRIPTION_EXPIRED',
                    'expired_at' => $expired->toIso8601String(),
                    'grace_ends' => $hardExpiry->toIso8601String(),
                ], 402);
            }
        }

        return $next($request);
    }
}
