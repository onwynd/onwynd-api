<?php

namespace App\Http\Middleware;

use App\Models\Payment\Subscription as PaymentSubscription;
use App\Models\QuotaSetting;
use App\Models\Subscription as LegacySubscription;
use App\Services\Currency\ExchangeRateService;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ActivityQuotaMiddleware
{
    private ExchangeRateService $exchangeRateService;

    public function __construct(ExchangeRateService $exchangeRateService)
    {
        $this->exchangeRateService = $exchangeRateService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Read-only requests never consume quota
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json([
                'error' => 'unauthenticated',
            ], 401);
        }

        $limit = $this->resolveLimit($user, 'daily_activity_limit', $this->getGlobalQuota('free_daily_activities', 1));
        if ($limit === null) {
            return $next($request);
        }

        $dateKey = Carbon::now('Africa/Lagos')->format('Y-m-d');
        $cacheKey = "quota:activities:{$user->id}:{$dateKey}";
        $count = (int) Cache::get($cacheKey, 0);

        // Add warning headers for progressive quota warnings
        $warningThreshold75 = (int) ($limit * 0.75);
        $warningThreshold90 = (int) ($limit * 0.9);
        $warningThreshold95 = (int) ($limit * 0.95);

        if ($count >= $limit) {
            // Check if user has used grace period today
            $graceKey = "quota:grace:{$user->id}:{$dateKey}";
            $graceUsed = Cache::get($graceKey, false);

            if (! $graceUsed) {
                // Allow one grace period per day for free users
                Cache::put($graceKey, true, Carbon::now('Africa/Lagos')->endOfDay());

                // Log grace period usage for analytics
                Log::info('User granted grace period for daily activity quota', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'route' => $request->path(),
                    'method' => $request->method(),
                    'date' => $dateKey,
                    'current_count' => $count,
                    'limit' => $limit,
                    'subscription_status' => $this->getUserSubscriptionStatus($user),
                    'grace_period_used' => true,
                    'event_type' => 'quota_grace_period_granted',
                ]);

                // Continue with the request but mark that grace was used
                $response = $next($request);

                if (method_exists($response, 'getStatusCode') && $response->getStatusCode() < 400) {
                    Cache::increment($cacheKey);
                    Cache::put($cacheKey, Cache::get($cacheKey, 1), Carbon::now('Africa/Lagos')->endOfDay());
                    Cache::increment("quota:stats:activities:{$dateKey}");
                }

                return $response;
            }

            // Grace period already used, show paywall
            Cache::increment("quota:stats:429_activities:{$dateKey}");

            // Log quota limit hit for analytics
            Log::warning('User hit daily activity quota limit', [
                'user_id' => $user->id,
                'email' => $user->email,
                'route' => $request->path(),
                'method' => $request->method(),
                'date' => $dateKey,
                'current_count' => $count,
                'limit' => $limit,
                'subscription_status' => $this->getUserSubscriptionStatus($user),
                'upsell_triggered' => true,
                'event_type' => 'quota_limit_reached',
            ]);

            // Get dynamic pricing from subscription plans
            $upsellData = $this->getUpsellData($user);

            return response()->json([
                'error' => 'daily_limit_reached',
                'upsell' => $upsellData,
                'quota_info' => [
                    'current' => $count,
                    'limit' => $limit,
                    'resets_at' => Carbon::now('Africa/Lagos')->endOfDay()->toIso8601String(),
                ],
            ], 429);
        }

        $response = $next($request);

        if (method_exists($response, 'getStatusCode') && $response->getStatusCode() < 400) {
            Cache::increment($cacheKey);
            Cache::put($cacheKey, Cache::get($cacheKey, 1), Carbon::now('Africa/Lagos')->endOfDay());
            Cache::increment("quota:stats:activities:{$dateKey}");

            $activityType = $this->determineActivityType($request);
            Log::info('Activity quota consumed', [
                'user_id' => $user->id,
                'activity_type' => $activityType,
                'route' => $request->path(),
                'method' => $request->method(),
                'date' => $dateKey,
                'count' => Cache::get($cacheKey, 1),
                'limit' => $limit,
            ]);

            // Add quota warning headers for progressive warnings
            if ($count + 1 >= $limit) {
                // Last activity - show final warning
                $response->headers->set('X-Quota-Warning', 'final');
                $response->headers->set('X-Quota-Remaining', '0');
                $response->headers->set('X-Quota-Resets-At', Carbon::now('Africa/Lagos')->endOfDay()->toIso8601String());
            } elseif ($count + 1 >= $warningThreshold95) {
                // 95% warning
                $response->headers->set('X-Quota-Warning', 'critical');
                $response->headers->set('X-Quota-Remaining', $limit - ($count + 1));
                $response->headers->set('X-Quota-Resets-At', Carbon::now('Africa/Lagos')->endOfDay()->toIso8601String());
            } elseif ($count + 1 >= $warningThreshold90) {
                // 90% warning
                $response->headers->set('X-Quota-Warning', 'high');
                $response->headers->set('X-Quota-Remaining', $limit - ($count + 1));
                $response->headers->set('X-Quota-Resets-At', Carbon::now('Africa/Lagos')->endOfDay()->toIso8601String());
            } elseif ($count + 1 >= $warningThreshold75) {
                // 75% warning
                $response->headers->set('X-Quota-Warning', 'medium');
                $response->headers->set('X-Quota-Remaining', $limit - ($count + 1));
                $response->headers->set('X-Quota-Resets-At', Carbon::now('Africa/Lagos')->endOfDay()->toIso8601String());
            }
        }

        return $response;
    }

    private function resolveLimit($user, string $featureKey, int $default): ?int
    {
        // Check if user has unlimited quota
        if ($user->has_unlimited_quota) {
            return null; // unlimited
        }

        // Check if user has custom daily activities quota
        if ($user->custom_daily_activities !== null) {
            // Check if override has expired
            if ($user->quota_override_expires_at === null ||
                Carbon::parse($user->quota_override_expires_at)->isFuture()) {
                return $user->custom_daily_activities;
            }
        }

        // Check subscription-based limits
        try {
            $sub = PaymentSubscription::where('user_id', $user->id)->active()->with('plan')->latest()->first();
            if ($sub && $sub->plan && is_array($sub->plan->features)) {
                $val = $sub->plan->features[$featureKey] ?? null;
                if ($val === null || $val === 0 || $val === 'unlimited') {
                    return null;
                }

                return (int) $val;
            }
        } catch (\Throwable $e) {
        }

        try {
            $legacy = LegacySubscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('current_period_start', '<=', now())
                ->where('current_period_end', '>=', now())
                ->with('plan')
                ->latest('current_period_end')
                ->first();
            if ($legacy && $legacy->plan && is_array($legacy->plan->features)) {
                $val = $legacy->plan->features[$featureKey] ?? null;
                if ($val === null || $val === 0 || $val === 'unlimited') {
                    return null;
                }

                return (int) $val;
            }
        } catch (\Throwable $e) {
        }

        return $default;
    }

    private function determineActivityType(Request $request): string
    {
        $path = $request->path();

        if (str_contains($path, 'mindful/sessions')) {
            return 'mindfulness_session';
        }
        if (str_contains($path, 'journal')) {
            return 'journal_entry';
        }
        if (str_contains($path, 'moods')) {
            return 'mood_check_in';
        }
        if (str_contains($path, 'habits')) {
            return 'habit_log';
        }
        if (str_contains($path, 'sleep')) {
            return 'sleep_log';
        }

        return 'wellness_activity';
    }

    private function getUserSubscriptionStatus($user): string
    {
        try {
            $sub = PaymentSubscription::where('user_id', $user->id)->active()->latest()->first();
            if ($sub) {
                return $sub->plan->name ?? 'paid';
            }

            $legacy = LegacySubscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('current_period_start', '<=', now())
                ->where('current_period_end', '>=', now())
                ->latest('current_period_end')
                ->first();
            if ($legacy) {
                return $legacy->plan->name ?? 'paid';
            }
        } catch (\Throwable $e) {
            // Fallback to free if we can't determine status
        }

        return 'free';
    }

    private function getUpsellData($user): array
    {
        // Get the most affordable paid plan for upsell
        try {
            $plans = \App\Models\SubscriptionPlan::where('status', 'active')
                ->where('price_monthly', '>', 0)
                ->orderBy('price_monthly', 'asc')
                ->limit(1)
                ->get();

            $plan = $plans->first();

            if ($plan) {
                // Get currency from user preferences or default to config
                $preferences = is_array($user->preferences) ? $user->preferences : json_decode($user->preferences ?? '{}', true);
                $currency = $preferences['currency'] ?? config('payment.currency.primary', 'NGN');
                $price = $this->formatPriceByCurrency($plan, $currency);
                $cta = 'Upgrade to '.$plan->name;
                $planName = $plan->name;
            } else {
                // Fallback prices with proper currency formatting
                $preferences = is_array($user->preferences) ? $user->preferences : json_decode($user->preferences ?? '{}', true);
                $currency = $preferences['currency'] ?? config('payment.currency.primary', 'NGN');
                $price = $this->formatFallbackPrice(2999, $currency);
                $cta = 'Unlock Unlimited Access';
                $planName = 'Premium';
            }
        } catch (\Throwable $e) {
            $preferences = is_array($user->preferences) ? $user->preferences : json_decode($user->preferences ?? '{}', true);
            $currency = $preferences['currency'] ?? config('payment.currency.primary', 'NGN');
            $price = $this->formatFallbackPrice(2999, $currency);
            $cta = 'Unlock Unlimited Access';
            $planName = 'Premium';
        }

        // Get user's current streak or activity level for personalization
        $userStats = $this->getUserStats($user);
        $personalizedMessage = $this->getPersonalizedMessage($userStats);

        return [
            'message' => $personalizedMessage,
            'subscribe_url' => '/subscription/upgrade',
            'cta' => $cta,
            'description' => 'Get unlimited mood tracking, mindfulness sessions, personalized content, and premium features designed for your mental wellness.',
            'price' => $price,
            'plan' => $planName,
            'benefits' => [
                'Unlimited mood and journal entries',
                'Premium mindfulness sessions',
                'Personalized wellness recommendations',
                'Priority customer support',
                'Advanced progress tracking',
            ],
            'grace_period_used' => true,
            'message_grace' => 'You had one free mood log today. Upgrade for unlimited access.',
            'feature' => 'unlimited_activities',
            'cta_url' => '/booking', // Alternative CTA for therapist booking
        ];
    }

    /**
     * Format price based on currency
     */
    private function formatPriceByCurrency($plan, string $currency): string
    {
        $price = null;

        switch (strtoupper($currency)) {
            case 'USD':
                $price = $plan->price_usd ?? $plan->price_monthly;
                $symbol = '$';
                break;
            case 'GBP':
                // Convert from USD to GBP using approximate rate (0.8)
                $usdPrice = $plan->price_usd ?? $plan->price_monthly;
                $price = $usdPrice * 0.8;
                $symbol = '£';
                break;
            case 'EUR':
                // Convert from USD to EUR using approximate rate (0.92)
                $usdPrice = $plan->price_usd ?? $plan->price_monthly;
                $price = $usdPrice * 0.92;
                $symbol = '€';
                break;
            case 'NGN':
            default:
                $price = $plan->price_ngn ?? $plan->price_monthly;
                $symbol = '₦';
                break;
        }

        return $symbol.number_format($price).'/mo';
    }

    /**
     * Format fallback price based on currency with dynamic conversion
     */
    private function formatFallbackPrice(float $price, string $currency): string
    {
        try {
            // Convert from NGN base price to target currency
            $convertedPrice = $this->exchangeRateService->getRate('NGN', $currency) * $price;

            switch (strtoupper($currency)) {
                case 'USD':
                    $symbol = '$';
                    break;
                case 'GBP':
                    $symbol = '£';
                    break;
                case 'EUR':
                    $symbol = '€';
                    break;
                case 'NGN':
                default:
                    $symbol = '₦';
                    break;
            }

            return $symbol.number_format($convertedPrice).'/mo';
        } catch (\Exception $e) {
            // Fallback to hardcoded values if exchange rate service fails
            Log::warning('Exchange rate conversion failed, using fallback pricing', [
                'currency' => $currency,
                'error' => $e->getMessage(),
            ]);

            switch (strtoupper($currency)) {
                case 'USD':
                    $symbol = '$';
                    $convertedPrice = $price * 0.0022; // Approximate NGN to USD rate
                    break;
                case 'GBP':
                    $symbol = '£';
                    $convertedPrice = $price * 0.0018; // Approximate NGN to GBP rate
                    break;
                case 'EUR':
                    $symbol = '€';
                    $convertedPrice = $price * 0.0021; // Approximate NGN to EUR rate
                    break;
                case 'NGN':
                default:
                    $symbol = '₦';
                    $convertedPrice = $price;
                    break;
            }

            return $symbol.number_format($convertedPrice).'/mo';
        }
    }

    private function getUserStats($user): array
    {
        try {
            // Get recent activity stats for personalization
            $recentActivities = \App\Models\ActivityLog::where('user_id', $user->id)
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->count();

            $moodLogs = \App\Models\MoodLog::where('user_id', $user->id)
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->count();

            $journalEntries = \App\Models\JournalEntry::where('user_id', $user->id)
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->count();

            return [
                'weekly_activities' => $recentActivities,
                'weekly_mood_logs' => $moodLogs,
                'weekly_journal_entries' => $journalEntries,
                'is_active_user' => $recentActivities > 10,
                'is_engaged' => $moodLogs > 3 || $journalEntries > 2,
            ];
        } catch (\Throwable $e) {
            return [
                'weekly_activities' => 0,
                'weekly_mood_logs' => 0,
                'weekly_journal_entries' => 0,
                'is_active_user' => false,
                'is_engaged' => false,
            ];
        }
    }

    private function getPersonalizedMessage(array $stats): string
    {
        $baseMessage = "You've hit your daily limit. Continue your mental health journey with unlimited access.";

        if ($stats['is_active_user']) {
            return "You're on a great streak! Don't let daily limits interrupt your mental wellness journey. Continue with unlimited access.";
        }

        if ($stats['is_engaged']) {
            return "We see you're actively working on your mental health. Remove daily limits and get personalized insights with unlimited access.";
        }

        if ($stats['weekly_activities'] > 5) {
            return "You're building healthy habits! Upgrade to continue your progress without daily interruptions.";
        }

        return $baseMessage;
    }

    private function getGlobalQuota(string $key, int $fallback): int
    {
        $quotas = Cache::remember('global_quotas', 3600, function () {
            $row = QuotaSetting::query()->orderBy('id', 'asc')->first();

            return [
                'free_daily_activities' => $row?->free_daily_activities ?? 1,
                'free_ai_messages' => $row?->free_ai_messages ?? 10,
            ];
        });

        return (int) ($quotas[$key] ?? $fallback);
    }
}
