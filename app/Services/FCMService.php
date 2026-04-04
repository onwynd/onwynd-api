<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FCMService — Firebase Cloud Messaging (HTTP v1 API) with OAuth2 service-account auth.
 *
 * Requires in .env:
 *   FIREBASE_PROJECT_ID=your-firebase-project-id
 *   FIREBASE_CREDENTIALS=/path/to/firebase-service-account.json
 *
 * Supported triggers (12 total):
 *  1. session_confirmed        → fires on session booking confirmation
 *  2. session_reminder         → fires 24h and 1h before session (scheduled job)
 *  3. session_cancelled        → fires on cancellation
 *  4. payment_success          → fires on Paystack charge.success
 *  5. payment_failed           → fires on Paystack charge.failed
 *  6. subscription_activated   → fires on plan activation
 *  7. subscription_expiring    → fires X days before renewal (scheduled job)
 *  8. streak_reminder          → fires at 8 pm if user has not checked in (scheduled job)
 *  9. streak_milestone         → fires at 7/30/60/90-day milestones
 * 10. score_update             → fires when Onwynd Score changes
 * 11. ai_quota_warning         → fires at 80% daily AI messages used
 * 12. ai_quota_exhausted       → fires at 100% daily AI messages used
 */
class FCMService
{
    private string $projectId;

    private string $credentialsPath;

    public function __construct()
    {
        $this->projectId = (string) config('services.firebase.project_id', env('FIREBASE_PROJECT_ID', ''));
        $this->credentialsPath = (string) config('services.firebase.credentials', env('FIREBASE_CREDENTIALS', ''));
    }

    // ─── Public Send API ───────────────────────────────────────────────────────

    /**
     * Send a push notification to all active devices belonging to a user.
     *
     * @param  array  $payload  { title, body, data? }
     */
    public function sendToUser(int $userId, array $payload): void
    {
        $tokens = DeviceToken::activeTokensForUser($userId);

        if (empty($tokens)) {
            return;
        }

        foreach ($tokens as $token) {
            $this->sendToToken($token, $payload);
        }
    }

    /**
     * Send a push notification to a Firebase topic (e.g. 'all_users').
     *
     * @param  array  $payload  { title, body, data? }
     */
    public function sendToTopic(string $topic, array $payload): void
    {
        $message = $this->buildMessage(null, $topic, $payload);
        $this->dispatch($message);
    }

    // ─── 12 Named Trigger Methods ──────────────────────────────────────────────

    /** Trigger 1: Session confirmed. */
    public function notifySessionConfirmed(int $userId, string $therapistName, string $scheduledAt): void
    {
        $this->sendToUser($userId, [
            'title' => 'Session Confirmed',
            'body' => "Your session with {$therapistName} is confirmed for {$scheduledAt}.",
            'data' => ['type' => 'session_confirmed', 'scheduled_at' => $scheduledAt],
        ]);
    }

    /** Trigger 2: Session reminder (24h or 1h before). */
    public function notifySessionReminder(int $userId, string $therapistName, string $scheduledAt, int $hoursAway): void
    {
        $timeLabel = $hoursAway >= 24 ? 'tomorrow' : "in {$hoursAway} hour".($hoursAway !== 1 ? 's' : '');
        $this->sendToUser($userId, [
            'title' => 'Session Reminder',
            'body' => "You have a session with {$therapistName} {$timeLabel} at {$scheduledAt}.",
            'data' => ['type' => 'session_reminder', 'scheduled_at' => $scheduledAt],
        ]);
    }

    /** Trigger 3: Session cancelled. */
    public function notifySessionCancelled(int $userId, string $therapistName, string $cancelledBy = 'system'): void
    {
        $byLabel = $cancelledBy === 'therapist' ? "by {$therapistName}" : 'by you';
        $this->sendToUser($userId, [
            'title' => 'Session Cancelled',
            'body' => "Your session with {$therapistName} has been cancelled {$byLabel}.",
            'data' => ['type' => 'session_cancelled', 'cancelled_by' => $cancelledBy],
        ]);
    }

    /** Trigger 4: Payment success. */
    public function notifyPaymentSuccess(int $userId, string $description, string $amount): void
    {
        $this->sendToUser($userId, [
            'title' => 'Payment Successful',
            'body' => "Payment of {$amount} for {$description} was successful.",
            'data' => ['type' => 'payment_success', 'amount' => $amount],
        ]);
    }

    /** Trigger 5: Payment failed. */
    public function notifyPaymentFailed(int $userId, ?string $reference = null): void
    {
        $this->sendToUser($userId, [
            'title' => 'Payment Failed',
            'body' => 'Your payment could not be processed. Please update your payment method to retain access.',
            'data' => ['type' => 'payment_failed', 'reference' => $reference ?? ''],
        ]);
    }

    /** Trigger 6: Subscription activated. */
    public function notifySubscriptionActivated(int $userId, string $planName): void
    {
        $this->sendToUser($userId, [
            'title' => 'Subscription Activated',
            'body' => "Your {$planName} subscription is now active. Welcome to Onwynd!",
            'data' => ['type' => 'subscription_activated', 'plan' => $planName],
        ]);
    }

    /** Trigger 7: Subscription expiring soon. */
    public function notifySubscriptionExpiring(int $userId, string $planName, int $daysLeft): void
    {
        $this->sendToUser($userId, [
            'title' => 'Subscription Renewing Soon',
            'body' => "Your {$planName} plan renews in {$daysLeft} day".($daysLeft !== 1 ? 's' : '').'. Ensure your payment method is up to date.',
            'data' => ['type' => 'subscription_expiring', 'days_left' => (string) $daysLeft],
        ]);
    }

    /** Trigger 8: Streak reminder (user hasn't checked in today). */
    public function notifyStreakReminder(int $userId, int $currentStreak): void
    {
        $body = $currentStreak > 0
            ? "Don't break your {$currentStreak}-day streak! Check in today to keep it going."
            : 'Start your wellness streak today. A small check-in goes a long way.';

        $this->sendToUser($userId, [
            'title' => 'Daily Check-in Reminder',
            'body' => $body,
            'data' => ['type' => 'streak_reminder', 'current_streak' => (string) $currentStreak],
        ]);
    }

    /** Trigger 9: Streak milestone (7 / 30 / 60 / 90 days). */
    public function notifyStreakMilestone(int $userId, int $days): void
    {
        $emojis = [7 => '🔥', 30 => '⭐', 60 => '💎', 90 => '🏆'];
        $emoji = $emojis[$days] ?? '🎉';
        $this->sendToUser($userId, [
            'title' => "{$emoji} {$days}-Day Streak!",
            'body' => "You've reached a {$days}-day wellness streak. That's incredible consistency!",
            'data' => ['type' => 'streak_milestone', 'days' => (string) $days],
        ]);
    }

    /** Trigger 10: Onwynd Score updated. */
    public function notifyScoreUpdate(int $userId, int $newScore, int $previousScore): void
    {
        $direction = $newScore > $previousScore ? 'improved' : 'changed';
        $this->sendToUser($userId, [
            'title' => 'Your Onwynd Score Updated',
            'body' => "Your wellness score has {$direction} to {$newScore}. Keep it up!",
            'data' => ['type' => 'score_update', 'score' => (string) $newScore],
        ]);
    }

    /** Trigger 11: AI quota warning (80% used). */
    public function notifyAiQuotaWarning(int $userId, int $remaining): void
    {
        $this->sendToUser($userId, [
            'title' => 'AI Messages Running Low',
            'body' => "You have {$remaining} AI message".($remaining !== 1 ? 's' : '').' left today. Upgrade for unlimited access.',
            'data' => ['type' => 'ai_quota_warning', 'remaining' => (string) $remaining],
        ]);
    }

    /** Trigger 12: AI quota exhausted (100% used). */
    public function notifyAiQuotaExhausted(int $userId): void
    {
        $this->sendToUser($userId, [
            'title' => "Today's AI Limit Reached",
            'body' => "You've used all your AI messages for today. Upgrade to Premium for unlimited daily access.",
            'data' => ['type' => 'ai_quota_exhausted'],
        ]);
    }

    // ─── Internal: token-level send ───────────────────────────────────────────

    private function sendToToken(string $token, array $payload): void
    {
        $message = $this->buildMessage($token, null, $payload);
        $this->dispatch($message);
    }

    private function buildMessage(?string $token, ?string $topic, array $payload): array
    {
        $notification = [
            'title' => $payload['title'] ?? 'Onwynd',
            'body' => $payload['body'] ?? '',
        ];

        $target = [];
        if ($token) {
            $target['token'] = $token;
        }
        if ($topic) {
            $target['topic'] = $topic;
        }

        $message = array_merge($target, [
            'notification' => $notification,
        ]);

        if (! empty($payload['data']) && is_array($payload['data'])) {
            // FCM data values must all be strings
            $message['data'] = array_map('strval', $payload['data']);
        }

        return ['message' => $message];
    }

    private function dispatch(array $body): void
    {
        if (! $this->projectId || ! $this->credentialsPath) {
            Log::warning('FCMService: FIREBASE_PROJECT_ID or FIREBASE_CREDENTIALS not configured — notification skipped.');

            return;
        }

        try {
            $accessToken = $this->getAccessToken();

            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->timeout(10)->post($url, $body);

            if (! $response->successful()) {
                $token = $body['message']['token'] ?? ($body['message']['topic'] ?? 'unknown');
                Log::warning('FCMService: dispatch failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'target' => $token,
                ]);

                // If the registration token is invalid/expired, deactivate it
                if ($response->status() === 404 || str_contains($response->body(), 'UNREGISTERED')) {
                    if (isset($body['message']['token'])) {
                        DeviceToken::where('token', $body['message']['token'])->update(['is_active' => false]);
                        Log::info('FCMService: deactivated unregistered token', ['token_prefix' => substr($body['message']['token'], 0, 20)]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('FCMService: exception during dispatch', ['error' => $e->getMessage()]);
        }
    }

    // ─── OAuth2 service-account token (cached 55 min) ─────────────────────────

    private function getAccessToken(): string
    {
        $cacheKey = 'fcm:access_token:v1';

        return Cache::remember($cacheKey, 55 * 60, function () {
            if (! file_exists($this->credentialsPath)) {
                throw new \RuntimeException("Firebase credentials file not found: {$this->credentialsPath}");
            }

            $credentials = json_decode(file_get_contents($this->credentialsPath), true);
            if (json_last_error() !== JSON_ERROR_NONE || empty($credentials['private_key'])) {
                throw new \RuntimeException('Firebase credentials JSON is invalid or missing private_key.');
            }

            $now = time();
            $claim = [
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ];

            $jwt = $this->buildJwt($claim, $credentials['private_key']);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException('FCMService: Failed to obtain OAuth2 access token: '.$response->body());
            }

            return $response->json('access_token');
        });
    }

    /**
     * Build a signed JWT for Google OAuth2 service-account flow.
     * Uses RS256 (RSA SHA-256) as required by Google.
     */
    private function buildJwt(array $claims, string $privateKeyPem): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode($claims));

        $signingInput = "{$header}.{$payload}";

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (! $privateKey) {
            throw new \RuntimeException('FCMService: Could not load RSA private key from service account.');
        }

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('FCMService: openssl_sign failed.');
        }

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
