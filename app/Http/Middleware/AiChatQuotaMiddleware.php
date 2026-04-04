<?php

namespace App\Http\Middleware;

use App\Services\AiQuotaService;
use App\Services\FCMService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AiChatQuotaMiddleware
{
    public function __construct(private AiQuotaService $quota) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        // Fix #6: If the frontend signals an active distress session, set the
        // distress flag BEFORE enforcing the quota so the extended daily limit
        // applies immediately on this very request (not just from the next one).
        // The controller RiskDetectionService still sets the flag autonomously
        // for future requests  this handles the explicit client-side signal.
        if ($request->boolean('is_distress_override')) {
            $this->quota->setDistressFlag($user);
        }

        $result = $this->quota->enforce($user);

        if (! $result['allowed']) {
            return response()->json($result['error'], 429);
        }

        $response = $next($request);

        // Only count successful responses
        if (method_exists($response, 'getStatusCode') && $response->getStatusCode() < 400) {
            $snapshot = $this->quota->increment($user);
            $this->dispatchQuotaNotification($user, $snapshot);
        }

        return $response;
    }

    /**
     * Fire a push notification when the user crosses the 80% warning threshold
     * or fully exhausts their daily AI quota.
     */
    private function dispatchQuotaNotification(mixed $user, array $snapshot): void
    {
        if ($snapshot['is_unlimited'] || $snapshot['limit'] === null) {
            return;
        }

        $count = $snapshot['count'];
        $limit = $snapshot['limit'];

        try {
            $fcm = app(FCMService::class);

            if ($count >= $limit) {
                $fcm->notifyAiQuotaExhausted($user->id);
            } elseif ($limit > 0 && ($count / $limit) >= 0.8) {
                $remaining = $limit - $count;
                $fcm->notifyAiQuotaWarning($user->id, $remaining);
            }
        } catch (\Throwable $e) {
            Log::warning('AiChatQuotaMiddleware: FCM notification failed', ['error' => $e->getMessage()]);
        }
    }
}
