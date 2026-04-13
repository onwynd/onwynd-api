<?php

namespace App\Services\PushNotification;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;

/**
 * Firebase Cloud Messaging (FCM) push notification sender.
 *
 * Sends push notifications to Web clients using Firebase Cloud Messaging.
 * Supports both web (via service workers) and device subscriptions.
 *
 * Requires Firebase setup:
 * - FIREBASE_PROJECT_ID env variable
 * - FIREBASE_CREDENTIALS json key file path in env
 * - Service account JSON file with Cloud Messaging permissions
 */
class WebPushService
{
    /**
     * Send a push notification to all subscriptions owned by a user.
     *
     * @param  array  $payload  { title, body, icon?, tag?, url? }
     */
    public function sendToUser(User $user, array $payload): void
    {
        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        foreach ($subscriptions as $subscription) {
            $this->sendToSubscription($subscription, $payload);
        }
    }

    /**
     * Send to a single PushSubscription using Firebase Cloud Messaging.
     */
    private function sendToSubscription(PushSubscription $subscription, array $payload): void
    {
        try {
            $projectId = config('services.firebase.project_id');
            $credentialsPath = config('services.firebase.credentials');

            if (! $projectId || ! $credentialsPath || ! file_exists($credentialsPath)) {
                Log::warning('Firebase not configured — push notifications disabled', [
                    'has_project_id' => (bool) $projectId,
                    'has_credentials' => (bool) $credentialsPath,
                    'credentials_exist' => file_exists($credentialsPath ?? ''),
                ]);

                return;
            }

            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();

            // Build FCM message from payload
            $message = [
                'webpush' => [
                    'headers' => [
                        'TTL' => '3600', // 1 hour
                    ],
                    'data' => [
                        'title' => $payload['title'] ?? 'Notification',
                        'body' => $payload['body'] ?? '',
                        'icon' => $payload['icon'] ?? '',
                        'tag' => $payload['tag'] ?? '',
                        'url' => $payload['url'] ?? '',
                    ],
                    'notification' => [
                        'title' => $payload['title'] ?? 'Notification',
                        'body' => $payload['body'] ?? '',
                        'icon' => $payload['icon'] ?? '',
                    ],
                ],
            ];

            // Send to the subscription endpoint (treated as FCM token for web)
            $result = $messaging->send([
                'token' => $subscription->endpoint,
                ...$message,
            ]);

            Log::info('FCM notification sent successfully', [
                'message_id' => $result,
                'user_id' => $subscription->user_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('WebPushService FCM error', [
                'user_id' => $subscription->user_id,
                'endpoint' => substr($subscription->endpoint ?? '', 0, 60),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            // Check if subscription is invalid/expired
            if (
                str_contains($e->getMessage(), 'registration token is invalid') ||
                str_contains($e->getMessage(), 'Mismatched credential format')
            ) {
                PushSubscription::where('endpoint', $subscription->endpoint)->delete();
                Log::info('Deleted invalid push subscription', ['user_id' => $subscription->user_id]);
            }
        }
    }
}
