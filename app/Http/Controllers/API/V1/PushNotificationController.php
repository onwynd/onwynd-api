<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Manages Web Push subscriptions for authenticated users.
 *
 * Routes:
 *   POST /notifications/subscribe   — store VAPID push subscription
 *   POST /notifications/unsubscribe — remove push subscription by endpoint
 */
class PushNotificationController extends Controller
{
    /**
     * Store or update a push subscription for the authenticated user.
     *
     * Payload (from PushSubscription.toJSON()):
     * {
     *   "subscription": {
     *     "endpoint": "https://...",
     *     "keys": { "auth": "...", "p256dh": "..." }
     *   }
     * }
     */
    public function subscribe(Request $request): JsonResponse
    {
        $user = $request->user();
        $sub = $request->input('subscription', []);

        $endpoint = $sub['endpoint'] ?? null;
        $authToken = $sub['keys']['auth'] ?? null;
        $publicKey = $sub['keys']['p256dh'] ?? null;

        if (! $endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Missing subscription endpoint',
            ], 422);
        }

        PushSubscription::updateOrCreate(
            ['user_id' => $user->id, 'endpoint' => $endpoint],
            [
                'auth_token' => $authToken,
                'public_key' => $publicKey,
                'content_encoding' => 'aesgcm',
            ]
        );

        Log::info('Push subscription registered', ['user_id' => $user->id]);

        return response()->json(['success' => true, 'message' => 'Push subscription saved']);
    }

    /**
     * Remove a push subscription by endpoint.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $user = $request->user();
        $sub = $request->input('subscription', []);
        $endpoint = $sub['endpoint'] ?? null;

        if ($endpoint) {
            PushSubscription::where('user_id', $user->id)
                ->where('endpoint', $endpoint)
                ->delete();
        } else {
            // No endpoint provided — remove all subscriptions for this user
            PushSubscription::where('user_id', $user->id)->delete();
        }

        return response()->json(['success' => true, 'message' => 'Push subscription removed']);
    }
}
