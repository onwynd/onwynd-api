<?php

namespace App\Services;

use App\Models\Payment\Subscription as PaymentSubscription;
use App\Models\QuotaSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AiQuotaService
{
    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Return full quota status for a user (used by the status endpoint).
     */
    public function getStatus($user): array
    {
        $dateKey = Carbon::now('Africa/Lagos')->format('Y-m-d');
        $cacheKey = "quota:ai:{$user->id}:{$dateKey}";
        $quotas = $this->globalSettings();
        $count = (int) Cache::get($cacheKey, 0);
        $resetsAt = Carbon::now('Africa/Lagos')->endOfDay()->toIso8601String();

        // 1. Premium unlimited
        if ($this->isPremiumUnlimited($user)) {
            return [
                'type' => 'premium',
                'limit' => null,
                'used' => $count,
                'remaining' => null,
                'resets_at' => $resetsAt,
                'nudge' => null,
            ];
        }

        // 2. Abuse (rapid-fire) – hard cap
        if ($this->isAbusing($user)) {
            $cap = $quotas['abuse_cap_messages'];
            $remaining = max(0, $cap - $count);

            return [
                'type' => 'abuse',
                'limit' => $cap,
                'used' => $count,
                'remaining' => $remaining,
                'resets_at' => $resetsAt,
                'nudge' => [
                    'type' => 'hard',
                    'message' => "You've been sending messages very quickly. Please slow down — we're here to support you thoughtfully.",
                    'cta' => null,
                ],
            ];
        }

        if ($user->has_unlimited_quota) {
            return [
                'type' => 'premium',
                'limit' => null,
                'used' => $count,
                'remaining' => null,
                'resets_at' => $resetsAt,
                'nudge' => null,
            ];
        }

        // 3. Compute effective limit
        [$type, $limit] = $this->effectiveLimit($user, $quotas, $dateKey);
        $remaining = max(0, $limit - $count);

        $nudge = $remaining <= 3 && $remaining > 0
            ? $this->softNudge($type, $remaining)
            : null;

        return [
            'type' => $type,
            'limit' => $limit,
            'used' => $count,
            'remaining' => $remaining,
            'resets_at' => $resetsAt,
            'nudge' => $nudge,
        ];
    }

    /**
     * Enforce quota. Returns ['allowed' => true] or ['allowed' => false, 'error' => [...]]
     */
    public function enforce($user): array
    {
        $dateKey = Carbon::now('Africa/Lagos')->format('Y-m-d');
        $cacheKey = "quota:ai:{$user->id}:{$dateKey}";
        $quotas = $this->globalSettings();
        $count = (int) Cache::get($cacheKey, 0);

        // Premium → unlimited
        if ($this->isPremiumUnlimited($user)) {
            return ['allowed' => true, 'error' => null];
        }

        // Crisis bypass: user in active distress window is NEVER quota-blocked (Section 9.4)
        if ($this->isInCrisisWindow($user)) {
            return ['allowed' => true, 'error' => null];
        }

        // Abuse check
        if ($this->isAbusing($user)) {
            $cap = $quotas['abuse_cap_messages'];
            if ($count >= $cap) {
                Cache::increment("quota:stats:429_ai:{$dateKey}");

                return [
                    'allowed' => false,
                    'error' => [
                        'error' => 'rate_limited',
                        'nudge_type' => 'abuse',
                        'message' => "You've been sending messages very quickly. Please take a breath and try again shortly.",
                        'upsell' => null,
                        'retry_after' => 60,
                    ],
                ];
            }
        }

        // Effective limit
        [$type, $limit] = $this->effectiveLimit($user, $quotas, $dateKey);

        if ($count >= $limit) {
            Cache::increment("quota:stats:429_ai:{$dateKey}");

            return [
                'allowed' => false,
                'error' => $this->limitReachedPayload($type),
            ];
        }

        return ['allowed' => true, 'error' => null];
    }

    /**
     * Increment usage counters after a successful message.
     * Returns ['count' => int, 'limit' => int|null, 'is_unlimited' => bool]
     * where limit === null means the user has no cap.
     */
    public function increment($user): array
    {
        $dateKey = Carbon::now('Africa/Lagos')->format('Y-m-d');
        $cacheKey = "quota:ai:{$user->id}:{$dateKey}";
        $rapidKey = "quota:ai:rapid:{$user->id}";
        $ttlKey = "quota:ai:rapid:{$user->id}:set";

        // Daily counter
        Cache::increment($cacheKey);
        Cache::put($cacheKey, Cache::get($cacheKey, 1), Carbon::now('Africa/Lagos')->endOfDay());
        Cache::increment("quota:stats:ai:{$dateKey}");

        // Rapid-fire counter (60-second window)
        Cache::increment($rapidKey);
        if (! Cache::has($ttlKey)) {
            Cache::put($rapidKey, Cache::get($rapidKey, 1), now()->addSeconds(60));
            Cache::put($ttlKey, true, now()->addSeconds(60));
        }

        $newCount = (int) Cache::get($cacheKey, 1);

        if ($this->isPremiumUnlimited($user) || $user->has_unlimited_quota) {
            return ['count' => $newCount, 'limit' => null, 'is_unlimited' => true];
        }

        $quotas = $this->globalSettings();
        [, $limit] = $this->effectiveLimit($user, $quotas, $dateKey);
        $effectiveLimit = $limit === PHP_INT_MAX ? null : $limit;

        return ['count' => $newCount, 'limit' => $effectiveLimit, 'is_unlimited' => $effectiveLimit === null];
    }

    /**
     * Flag this user as being in distress.
     *
     * The bypass lasts 72 hours from the time of the most recent flag.
     * Calling this again resets the 72-hour window (e.g. crisis escalates).
     * Clinical advisors can clear the flag manually via the admin panel.
     *
     * CRISIS BYPASS RULE (Section 9.4): a user flagged for distress is
     * NEVER quota-blocked for the duration of the active window.
     */
    public function setDistressFlag($user): void
    {
        // Key is user-scoped, not date-scoped — persists across day boundaries
        $distressKey = "quota:distress:{$user->id}";
        Cache::put($distressKey, now()->toIso8601String(), now()->addHours(72));
    }

    /**
     * Check whether the user is currently within a crisis bypass window.
     * Returns true if a distress flag was set within the last 72 hours.
     */
    public function isInCrisisWindow($user): bool
    {
        return Cache::has("quota:distress:{$user->id}");
    }

    /**
     * Manually clear a crisis bypass (e.g. clinical advisor marks resolved).
     */
    public function clearDistressFlag($user): void
    {
        Cache::forget("quota:distress:{$user->id}");
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function globalSettings(): array
    {
        return Cache::remember('global_quotas_v2', 3600, function () {
            $row = QuotaSetting::query()->orderBy('id')->first();

            return [
                'free_daily_activities' => (int) ($row?->free_daily_activities ?? 2),
                'free_ai_messages' => (int) ($row?->free_ai_messages ?? 10),
                'new_user_ai_messages' => (int) ($row?->new_user_ai_messages ?? 15),
                'new_user_days' => (int) ($row?->new_user_days ?? 7),
                'distress_extension_messages' => (int) ($row?->distress_extension_messages ?? 5),
                'abuse_cap_messages' => (int) ($row?->abuse_cap_messages ?? 5),
            ];
        });
    }

    private function isPremiumUnlimited($user): bool
    {
        try {
            $sub = PaymentSubscription::where('user_id', $user->id)
                ->active()
                ->with('plan')
                ->latest()
                ->first();

            if ($sub && $sub->plan && is_array($sub->plan->features)) {
                $val = $sub->plan->features['ai_message_limit'] ?? null;

                return $val === null || $val === 0 || $val === 'unlimited';
            }
        } catch (\Throwable) {
        }

        return false;
    }

    private function isAbusing($user): bool
    {
        $rapidKey = "quota:ai:rapid:{$user->id}";
        $count = (int) Cache::get($rapidKey, 0);

        return $count > 3;
    }

    /**
     * Returns [type, effectiveLimit] based on user age + distress flag + custom quotas.
     */
    private function effectiveLimit($user, array $quotas, string $dateKey): array
    {
        // Check if user has unlimited quota
        if ($user->has_unlimited_quota) {
            return ['unlimited', PHP_INT_MAX];
        }

        // Check if user has custom quota override
        if ($user->custom_ai_messages !== null) {
            // Check if override has expired
            if ($user->quota_override_expires_at === null ||
                Carbon::parse($user->quota_override_expires_at)->isFuture()) {
                return ['returning', $user->custom_ai_messages + ($user->referral_ai_bonus ?? 0)];
            }
        }

        // Standard quota calculation
        $isNew = Carbon::parse($user->created_at)->diffInDays(Carbon::now()) <= $quotas['new_user_days'];
        $base = $isNew ? $quotas['new_user_ai_messages'] : $quotas['free_ai_messages'];
        $type = $isNew ? 'new_user' : 'returning';

        // 72-hour crisis bypass window (key is user-scoped, not date-scoped)
        if ($this->isInCrisisWindow($user)) {
            $base += $quotas['distress_extension_messages'];
            $type = 'distress';
        }

        // Add any referral bonus chats on top of the computed base
        $base += ($user->referral_ai_bonus ?? 0);

        return [$type, $base];
    }

    private function softNudge(string $type, int $remaining): array
    {
        $messages = [
            'new_user' => "Almost at today's limit — {$remaining} message(s) left. Want to keep going tomorrow or talk to someone real right now?",
            'returning' => "You're close to today's limit — {$remaining} message(s) left. Want to unlock unlimited support?",
            'distress' => "We extended your messages today because we care. {$remaining} left — a real therapist can support you further.",
        ];

        $ctas = [
            'new_user' => ['label' => 'Book a Session',     'url' => '/therapist-booking'],
            'returning' => ['label' => 'Unlock Unlimited',   'url' => '/pricing'],
            'distress' => ['label' => 'Talk to a Therapist', 'url' => '/therapist-booking'],
        ];

        return [
            'type' => 'soft',
            'message' => $messages[$type] ?? "{$remaining} message(s) left today.",
            'cta' => $ctas[$type] ?? null,
        ];
    }

    private function limitReachedPayload(string $type): array
    {
        $limitMessage = "You've reached your daily limit for today. Want to keep going tomorrow, or talk to someone real right now?";

        $messages = [
            'new_user' => $limitMessage,
            'returning' => $limitMessage,
            'distress' => "You've used all your extended messages for today. Want to keep going tomorrow, or talk to a real therapist right now?",
        ];

        $upsells = [
            'new_user' => ['message' => 'Continue your wellness journey',  'cta_url' => '/therapist-booking', 'subscribe_url' => '/pricing', 'upgrade_url' => '/pricing'],
            'returning' => ['message' => 'Unlock unlimited AI companion',   'cta_url' => '/therapist-booking', 'subscribe_url' => '/pricing', 'upgrade_url' => '/pricing'],
            'distress' => ['message' => 'A real therapist is here for you', 'cta_url' => '/therapist-booking', 'subscribe_url' => '/pricing', 'upgrade_url' => '/pricing'],
        ];

        return [
            'error' => 'daily_ai_limit_reached',
            'nudge_type' => $type,
            'message' => $messages[$type] ?? "You've reached your daily AI chat limit.",
            'upsell' => $upsells[$type] ?? null,
        ];
    }
}
