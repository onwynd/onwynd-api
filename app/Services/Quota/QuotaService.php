<?php

namespace App\Services\Quota;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class QuotaService
{
    private const CACHE_TTL = 86400; // 24 hours

    private const WARNING_THRESHOLD = 0.8; // 80% of quota

    /**
     * Get comprehensive quota information for a user or anonymous session
     */
    public function getQuotaInfo(?User $user = null, ?string $anonymousId = null): array
    {
        $isAnonymous = $user === null;
        $identifier = $isAnonymous ? $anonymousId : $user->id;
        $type = $isAnonymous ? 'anonymous' : 'user';

        $today = now('Africa/Lagos')->format('Y-m-d');
        $thisMonth = now('Africa/Lagos')->format('Y-m');
        $thisYear = now('Africa/Lagos')->format('Y');

        // Warm up cache keys to prevent cache misses
        $this->warmQuotaCache($user, $anonymousId);

        // Get quota limits
        $limits = $this->getQuotaLimits($user, $isAnonymous);

        // Get current usage
        $usage = $this->getCurrentUsage($identifier, $type, $today, $thisMonth, $thisYear);

        // Calculate remaining quotas
        $remaining = $this->calculateRemainingQuotas($limits, $usage);

        // Check warning thresholds
        $warnings = $this->checkWarningThresholds($limits, $usage);

        return [
            'type' => $type,
            'limits' => $limits,
            'usage' => $usage,
            'remaining' => $remaining,
            'warnings' => $warnings,
            'percentages' => $this->calculateUsagePercentages($limits, $usage),
        ];
    }

    /**
     * Get quota limits based on user type and subscription
     */
    private function getQuotaLimits(?User $user = null, bool $isAnonymous = false): array
    {
        if ($isAnonymous) {
            return [
                'daily_sessions' => (int) (Setting::where('key', 'anonymous_sessions_daily')->first()->value ?? 3),
                'monthly_sessions' => (int) (Setting::where('key', 'anonymous_sessions_monthly')->first()->value ?? 10),
                'yearly_sessions' => (int) (Setting::where('key', 'anonymous_sessions_yearly')->first()->value ?? 50),
                'ai_messages_daily' => (int) (Setting::where('key', 'anonymous_ai_messages_daily')->first()->value ?? 5),
                'ai_messages_monthly' => (int) (Setting::where('key', 'anonymous_ai_messages_monthly')->first()->value ?? 20),
            ];
        }

        // 1. Check for Organization/Institutional Quota
        $orgMember = $user->activeOrganizationMembership();
        if ($orgMember) {
            $org = $orgMember->organization;

            // If it's a university student, apply the semester limit (e.g. 2 sessions)
            if ($org->type === 'university') {
                return [
                    'daily_sessions' => 1,
                    'monthly_sessions' => 2, // Hard limit for students per semester/month
                    'yearly_sessions' => $org->session_credits_per_student ?? 2,
                    'ai_messages_daily' => -1, // Students usually get unlimited AI
                    'ai_messages_monthly' => -1,
                ];
            }

            // For corporate employees, use the member's individual limit
            return [
                'daily_sessions' => 1,
                'monthly_sessions' => $orgMember->sessions_limit ?? 4,
                'yearly_sessions' => ($orgMember->sessions_limit ?? 4) * 12,
                'ai_messages_daily' => -1,
                'ai_messages_monthly' => -1,
            ];
        }

        // 2. For regular B2C users, check subscription and custom quotas
        $subscription = $user->activeSubscription();
        $plan = $user->subscriptionPlan;

        // Check for custom user quotas first
        if ($user->has_unlimited_quota) {
            return [
                'daily_sessions' => -1, // Unlimited
                'monthly_sessions' => -1,
                'yearly_sessions' => -1,
                'ai_messages_daily' => -1,
                'ai_messages_monthly' => -1,
            ];
        }

        $limits = [
            'daily_sessions' => $user->custom_daily_sessions ?? 1,
            'monthly_sessions' => $user->custom_monthly_sessions ?? ($plan ? $plan->monthly_sessions : 0),
            'yearly_sessions' => $user->custom_yearly_sessions ?? ($plan ? $plan->monthly_sessions * 12 : 0),
            'ai_messages_daily' => ($user->custom_ai_messages ?? ($plan ? $plan->ai_message_limit : 10)) + ($user->referral_ai_bonus ?? 0),
            'ai_messages_monthly' => ($user->custom_ai_messages ?? ($plan ? $plan->ai_message_limit * 30 : 300)) + ($user->referral_ai_bonus ?? 0),
        ];

        // Apply grace period if applicable
        if ($user->quota_override_expires_at && $user->quota_override_expires_at->isFuture()) {
            foreach ($limits as $key => $value) {
                if ($key !== 'daily_sessions') { // Don't override daily limits during grace period
                    $limits[$key] = max($value, 20); // Default grace limit
                }
            }
        }

        return $limits;
    }

    /**
     * Get current usage from cache and database
     */
    private function getCurrentUsage(string $identifier, string $type, string $today, string $thisMonth, string $thisYear): array
    {
        $cachePrefix = "quota:{$type}:{$identifier}";

        return [
            'daily_sessions' => $this->getSessionCount($identifier, $type, $today, 'daily'),
            'monthly_sessions' => $this->getSessionCount($identifier, $type, $thisMonth, 'monthly'),
            'yearly_sessions' => $this->getSessionCount($identifier, $type, $thisYear, 'yearly'),
            'ai_messages_daily' => (int) Cache::get("{$cachePrefix}:ai_messages:{$today}", 0),
            'ai_messages_monthly' => (int) Cache::get("{$cachePrefix}:ai_messages:{$thisMonth}", 0),
        ];
    }

    /**
     * Get session count from database for specified period
     */
    private function getSessionCount(string $identifier, string $type, string $period, string $periodType): int
    {
        $query = \App\Models\TherapySession::where('status', '!=', 'cancelled');

        if ($type === 'anonymous') {
            $query->where('is_anonymous', true);
            // For anonymous sessions, we use the standardized cache-based counter
            $cachePrefix = "quota:anonymous:{$identifier}";

            return (int) Cache::get("{$cachePrefix}:sessions:{$period}", 0);
        } else {
            $query->where('patient_id', $identifier);
        }

        // Apply period filter
        switch ($periodType) {
            case 'daily':
                $query->whereDate('scheduled_at', $period);
                break;
            case 'monthly':
                $query->whereYear('scheduled_at', substr($period, 0, 4))
                    ->whereMonth('scheduled_at', substr($period, 5, 2));
                break;
            case 'yearly':
                $query->whereYear('scheduled_at', $period);
                break;
        }

        return $query->count();
    }

    /**
     * Calculate remaining quotas
     */
    private function calculateRemainingQuotas(array $limits, array $usage): array
    {
        $remaining = [];

        foreach ($limits as $key => $limit) {
            if ($limit === -1) { // Unlimited
                $remaining[$key] = -1;
            } else {
                $remaining[$key] = max(0, $limit - ($usage[$key] ?? 0));
            }
        }

        return $remaining;
    }

    /**
     * Check if user is approaching quota limits
     */
    private function checkWarningThresholds(array $limits, array $usage): array
    {
        $warnings = [];

        foreach ($limits as $key => $limit) {
            if ($limit === -1 || $limit === 0) {
                continue;
            } // Skip unlimited or zero limits

            $currentUsage = $usage[$key] ?? 0;
            $percentage = $limit > 0 ? ($currentUsage / $limit) : 1;

            if ($percentage >= 1) {
                $warnings[$key] = [
                    'level' => 'critical',
                    'message' => "Quota exceeded for {$key}",
                    'usage' => $currentUsage,
                    'limit' => $limit,
                ];
            } elseif ($percentage >= self::WARNING_THRESHOLD) {
                $warnings[$key] = [
                    'level' => 'warning',
                    'message' => "Approaching quota limit for {$key}",
                    'usage' => $currentUsage,
                    'limit' => $limit,
                    'remaining' => $limit - $currentUsage,
                ];
            }
        }

        return $warnings;
    }

    /**
     * Calculate usage percentages
     */
    private function calculateUsagePercentages(array $limits, array $usage): array
    {
        $percentages = [];

        foreach ($limits as $key => $limit) {
            if ($limit === -1) {
                $percentages[$key] = 0; // 0% for unlimited
            } elseif ($limit > 0) {
                $percentages[$key] = min(100, round((($usage[$key] ?? 0) / $limit) * 100));
            } else {
                $percentages[$key] = 100; // 100% for zero limit
            }
        }

        return $percentages;
    }

    /**
     * Increment quota usage
     */
    public function incrementQuota(string $type, string $identifier, string $quotaType, int $amount = 1): void
    {
        $today = now('Africa/Lagos')->format('Y-m-d');
        $thisMonth = now('Africa/Lagos')->format('Y-m');

        $cacheKey = "quota:{$type}:{$identifier}";

        switch ($quotaType) {
            case 'ai_message':
                Cache::increment("{$cacheKey}:ai_messages:{$today}");
                Cache::increment("{$cacheKey}:ai_messages:{$thisMonth}");
                // Set TTL for daily
                Cache::put("{$cacheKey}:ai_messages:{$today}",
                    Cache::get("{$cacheKey}:ai_messages:{$today}"),
                    self::CACHE_TTL);
                break;

            case 'session':
                if ($type === 'anonymous') {
                    // For anonymous sessions, increment the daily counter
                    $dateKey = now('Africa/Lagos')->format('Y-m-d');
                    $countKey = "quota:anonymous_sessions:{$dateKey}";
                    Cache::increment($countKey);
                    Cache::put($countKey, Cache::get($countKey), now('Africa/Lagos')->endOfDay());
                }
                break;
        }
    }

    /**
     * Check if quota would be exceeded before allowing an action
     */
    public function wouldExceedQuota(string $type, string $identifier, string $quotaType, int $amount = 1): array
    {
        $quotaInfo = $this->getQuotaInfo(
            $type === 'user' ? \App\Models\User::find($identifier) : null,
            $type === 'anonymous' ? $identifier : null
        );

        $quotaKey = str_replace('_', '_', $quotaType);
        $currentRemaining = $quotaInfo['remaining'][$quotaKey] ?? 0;

        if ($currentRemaining === -1) {
            return ['would_exceed' => false, 'remaining' => -1];
        }

        $wouldExceed = $currentRemaining < $amount;

        return [
            'would_exceed' => $wouldExceed,
            'remaining' => $currentRemaining,
            'requested' => $amount,
            'message' => $wouldExceed ? "This would exceed your {$quotaType} quota" : null,
        ];
    }

    /**
     * Get quota statistics for analytics
     */
    public function getQuotaStats(string $period = 'daily'): array
    {
        $dateFormat = match ($period) {
            'daily' => 'Y-m-d',
            'monthly' => 'Y-m',
            'yearly' => 'Y',
        };

        $currentPeriod = now('Africa/Lagos')->format($dateFormat);

        return [
            'anonymous_sessions' => (int) Cache::get("quota:stats:anonymous_sessions:{$currentPeriod}", 0),
            'user_sessions' => (int) Cache::get("quota:stats:user_sessions:{$currentPeriod}", 0),
            'ai_messages' => (int) Cache::get("quota:stats:ai_messages:{$currentPeriod}", 0),
            'quota_violations' => (int) Cache::get("quota:stats:violations:{$currentPeriod}", 0),
        ];
    }

    /**
     * Get usage history for authenticated users
     */
    public function getUsageHistory(\App\Models\User $user, int $days = 30): array
    {
        $history = [];
        $endDate = now('Africa/Lagos')->startOfDay();
        $startDate = $endDate->copy()->subDays($days - 1);

        // Get user's sessions for the period
        $sessions = \App\Models\TherapySession::where('patient_id', $user->id)
            ->whereBetween('scheduled_at', [$startDate, $endDate->copy()->endOfDay()])
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('scheduled_at', 'desc')
            ->get();

        // Group by date
        $dailyUsage = [];
        foreach ($sessions as $session) {
            $date = $session->scheduled_at->format('Y-m-d');
            if (! isset($dailyUsage[$date])) {
                $dailyUsage[$date] = 0;
            }
            $dailyUsage[$date]++;
        }

        // Build history array
        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $history[] = [
                'date' => $dateStr,
                'sessions' => $dailyUsage[$dateStr] ?? 0,
                'ai_messages' => 0, // Will be implemented when AI messaging is added
            ];
        }

        return $history;
    }

    /**
     * Increment quota usage for users or anonymous sessions
     */
    public function incrementQuotaUsage(?\App\Models\User $user = null, ?string $anonymousId = null, string $type = 'session'): void
    {
        $isAnonymous = $user === null;
        $identifier = $isAnonymous ? $anonymousId : $user->id;
        $identifierType = $isAnonymous ? 'anonymous' : 'user';

        $today = now('Africa/Lagos')->format('Y-m-d');
        $thisMonth = now('Africa/Lagos')->format('Y-m');
        $thisYear = now('Africa/Lagos')->format('Y');

        switch ($type) {
            case 'session':
                if ($isAnonymous) {
                    // Increment anonymous session counters using standardized key pattern
                    $cachePrefix = "quota:anonymous:{$identifier}";
                    $dailyKey = "{$cachePrefix}:sessions:{$today}";
                    $monthlyKey = "{$cachePrefix}:sessions:{$thisMonth}";
                    $yearlyKey = "{$cachePrefix}:sessions:{$thisYear}";

                    // Ensure cache keys exist before incrementing
                    $this->warmCacheKey($dailyKey, 0, now('Africa/Lagos')->endOfDay());
                    $this->warmCacheKey($monthlyKey, 0, now('Africa/Lagos')->endOfMonth());
                    $this->warmCacheKey($yearlyKey, 0, now('Africa/Lagos')->endOfYear());

                    Cache::increment($dailyKey);
                    Cache::increment($monthlyKey);
                    Cache::increment($yearlyKey);

                    // Update TTL after increment
                    Cache::put($dailyKey, Cache::get($dailyKey), now('Africa/Lagos')->endOfDay());
                    Cache::put($monthlyKey, Cache::get($monthlyKey), now('Africa/Lagos')->endOfMonth());
                    Cache::put($yearlyKey, Cache::get($yearlyKey), now('Africa/Lagos')->endOfYear());
                } else {
                    // Increment user session counters using standardized key pattern
                    $cachePrefix = "quota:user:{$user->id}";
                    $dailyKey = "{$cachePrefix}:sessions:{$today}";
                    $monthlyKey = "{$cachePrefix}:sessions:{$thisMonth}";
                    $yearlyKey = "{$cachePrefix}:sessions:{$thisYear}";

                    // Ensure cache keys exist before incrementing
                    $this->warmCacheKey($dailyKey, 0, now('Africa/Lagos')->endOfDay());
                    $this->warmCacheKey($monthlyKey, 0, now('Africa/Lagos')->endOfMonth());
                    $this->warmCacheKey($yearlyKey, 0, now('Africa/Lagos')->endOfYear());

                    Cache::increment($dailyKey);
                    Cache::increment($monthlyKey);
                    Cache::increment($yearlyKey);

                    // Update TTL after increment
                    Cache::put($dailyKey, Cache::get($dailyKey), now('Africa/Lagos')->endOfDay());
                    Cache::put($monthlyKey, Cache::get($monthlyKey), now('Africa/Lagos')->endOfMonth());
                    Cache::put($yearlyKey, Cache::get($yearlyKey), now('Africa/Lagos')->endOfYear());
                }
                break;

            case 'ai_message':
                if ($isAnonymous) {
                    // Increment anonymous AI message counters using standardized key pattern
                    $cachePrefix = "quota:anonymous:{$identifier}";
                    $dailyKey = "{$cachePrefix}:ai_messages:{$today}";
                    $monthlyKey = "{$cachePrefix}:ai_messages:{$thisMonth}";

                    // Ensure cache keys exist before incrementing
                    $this->warmCacheKey($dailyKey, 0, self::CACHE_TTL);
                    $this->warmCacheKey($monthlyKey, 0, now('Africa/Lagos')->endOfMonth());

                    Cache::increment($dailyKey);
                    Cache::increment($monthlyKey);

                    // Update TTL after increment
                    Cache::put($dailyKey, Cache::get($dailyKey), self::CACHE_TTL);
                    Cache::put($monthlyKey, Cache::get($monthlyKey), now('Africa/Lagos')->endOfMonth());
                } else {
                    // Increment user AI message counters using standardized key pattern
                    $cachePrefix = "quota:user:{$user->id}";
                    $dailyKey = "{$cachePrefix}:ai_messages:{$today}";
                    $monthlyKey = "{$cachePrefix}:ai_messages:{$thisMonth}";

                    // Ensure cache keys exist before incrementing
                    $this->warmCacheKey($dailyKey, 0, self::CACHE_TTL);
                    $this->warmCacheKey($monthlyKey, 0, now('Africa/Lagos')->endOfMonth());

                    Cache::increment($dailyKey);
                    Cache::increment($monthlyKey);

                    // Update TTL after increment
                    Cache::put($dailyKey, Cache::get($dailyKey), self::CACHE_TTL);
                    Cache::put($monthlyKey, Cache::get($monthlyKey), now('Africa/Lagos')->endOfMonth());
                }
                break;
        }
    }

    /**
     * Warm up cache key to ensure it exists before incrementing
     * This prevents cache misses and ensures consistent behavior
     */
    private function warmCacheKey(string $key, $defaultValue, $ttl): void
    {
        if (! Cache::has($key)) {
            Cache::put($key, $defaultValue, $ttl);
        }
    }

    /**
     * Warm up all quota cache keys for a user or anonymous session
     * This ensures all keys exist and have proper TTLs
     */
    public function warmQuotaCache(?\App\Models\User $user = null, ?string $anonymousId = null): void
    {
        $isAnonymous = $user === null;
        $identifier = $isAnonymous ? $anonymousId : $user->id;
        $today = now('Africa/Lagos')->format('Y-m-d');
        $thisMonth = now('Africa/Lagos')->format('Y-m');
        $thisYear = now('Africa/Lagos')->format('Y');

        if ($isAnonymous) {
            $cachePrefix = "quota:anonymous:{$identifier}";

            // Warm up session quota keys
            $this->warmCacheKey("{$cachePrefix}:sessions:{$today}", 0, now('Africa/Lagos')->endOfDay());
            $this->warmCacheKey("{$cachePrefix}:sessions:{$thisMonth}", 0, now('Africa/Lagos')->endOfMonth());
            $this->warmCacheKey("{$cachePrefix}:sessions:{$thisYear}", 0, now('Africa/Lagos')->endOfYear());

            // Warm up AI message quota keys
            $this->warmCacheKey("{$cachePrefix}:ai_messages:{$today}", 0, self::CACHE_TTL);
            $this->warmCacheKey("{$cachePrefix}:ai_messages:{$thisMonth}", 0, now('Africa/Lagos')->endOfMonth());
        } else {
            $cachePrefix = "quota:user:{$user->id}";

            // Warm up session quota keys
            $this->warmCacheKey("{$cachePrefix}:sessions:{$today}", 0, now('Africa/Lagos')->endOfDay());
            $this->warmCacheKey("{$cachePrefix}:sessions:{$thisMonth}", 0, now('Africa/Lagos')->endOfMonth());
            $this->warmCacheKey("{$cachePrefix}:sessions:{$thisYear}", 0, now('Africa/Lagos')->endOfYear());

            // Warm up AI message quota keys
            $this->warmCacheKey("{$cachePrefix}:ai_messages:{$today}", 0, self::CACHE_TTL);
            $this->warmCacheKey("{$cachePrefix}:ai_messages:{$thisMonth}", 0, now('Africa/Lagos')->endOfMonth());
        }
    }
}
