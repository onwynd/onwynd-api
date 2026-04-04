<?php

namespace App\Services\PushNotification;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Web Push sender.
 *
 * Sends a push notification payload to a user's registered push subscription
 * endpoints using the Web Push Protocol.
 *
 * Requires VAPID keys in env:
 *   VAPID_PUBLIC_KEY=<base64url-encoded public key>
 *   VAPID_PRIVATE_KEY=<base64url-encoded private key>
 *   VAPID_SUBJECT=mailto:admin@onwynd.com
 *
 * For production, install `minishlink/web-push` via composer for full VAPID
 * encryption support:
 *   composer require minishlink/web-push
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
        $subscriptions = PushSubscription::where('user_id', $user->id)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        foreach ($subscriptions as $subscription) {
            $this->sendToSubscription($subscription, $payload);
        }
    }

    /**
     * Send to a single PushSubscription.
     */
    private function sendToSubscription(PushSubscription $subscription, array $payload): void
    {
        try {
            // Check if minishlink/web-push is available
            if (! class_exists(\Minishlink\WebPush\WebPush::class)) {
                // Fallback: log the notification (install web-push package to enable actual sending)
                Log::info('WebPush (install minishlink/web-push to enable): notification queued', [
                    'user_id' => $subscription->user_id,
                    'endpoint' => substr($subscription->endpoint, 0, 60).'...',
                    'payload' => $payload,
                ]);

                return;
            }

            $vapidPublic = config('services.vapid.public_key');
            $vapidPrivate = config('services.vapid.private_key');
            $vapidSubject = config('services.vapid.subject', 'mailto:admin@onwynd.com');

            if (! $vapidPublic || ! $vapidPrivate) {
                Log::warning('VAPID keys not configured — push notifications disabled');

                return;
            }

            $webPush = new \Minishlink\WebPush\WebPush([
                'VAPID' => [
                    'subject' => $vapidSubject,
                    'publicKey' => $vapidPublic,
                    'privateKey' => $vapidPrivate,
                ],
            ]);

            $sub = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $subscription->endpoint,
                'contentEncoding' => $subscription->content_encoding,
                'keys' => [
                    'auth' => $subscription->auth_token,
                    'p256dh' => $subscription->public_key,
                ],
            ]);

            $webPush->queueNotification($sub, json_encode($payload));

            foreach ($webPush->flush() as $report) {
                if (! $report->isSuccess()) {
                    Log::warning('WebPush send failed', [
                        'reason' => $report->getReason(),
                        'endpoint' => $report->getEndpoint(),
                    ]);
                    // Expired/invalid subscription — remove it
                    if ($report->isSubscriptionExpired()) {
                        PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('WebPushService error', [
                'user_id' => $subscription->user_id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
