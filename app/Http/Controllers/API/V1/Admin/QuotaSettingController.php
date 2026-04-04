<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\QuotaSetting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QuotaSettingController extends BaseController
{
    // The cache key must match AiQuotaService::globalSettings()
    private const CACHE_KEY = 'global_quotas_v2';

    public function show()
    {
        $row = QuotaSetting::query()->orderBy('id', 'asc')->first();
        if (! $row) {
            $row = QuotaSetting::create([
                'free_daily_activities' => 2,
                'free_ai_messages' => 10,
                'new_user_ai_messages' => 15,
                'new_user_days' => 7,
                'distress_extension_messages' => 5,
                'abuse_cap_messages' => 5,
                'corporate_grace_period_days' => 14,
            ]);
        }

        return $this->sendResponse($row, 'Quota settings retrieved.');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'free_daily_activities' => 'required|integer|min:0',
            'free_ai_messages' => 'required|integer|min:0',
            'new_user_ai_messages' => 'required|integer|min:0',
            'new_user_days' => 'required|integer|min:1',
            'distress_extension_messages' => 'required|integer|min:0',
            'abuse_cap_messages' => 'required|integer|min:1',
            'corporate_grace_period_days' => 'nullable|integer|min:0',
        ]);

        $row = QuotaSetting::query()->orderBy('id', 'asc')->first();
        if ($row) {
            $row->update($data);
        } else {
            $row = QuotaSetting::create($data);
        }

        // Bust the cache used by AiQuotaService
        Cache::forget(self::CACHE_KEY);

        return $this->sendResponse($row, 'Quota settings updated.');
    }

    public function overview()
    {
        $quotas = Cache::remember(self::CACHE_KEY, 3600, function () {
            $row = QuotaSetting::query()->orderBy('id', 'asc')->first();

            return [
                'free_daily_activities' => $row?->free_daily_activities ?? 2,
                'free_ai_messages' => $row?->free_ai_messages ?? 10,
                'new_user_ai_messages' => $row?->new_user_ai_messages ?? 15,
                'new_user_days' => $row?->new_user_days ?? 7,
                'distress_extension_messages' => $row?->distress_extension_messages ?? 5,
                'abuse_cap_messages' => $row?->abuse_cap_messages ?? 5,
            ];
        });

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->get(['id', 'name', 'slug', 'features', 'billing_interval']);

        $dateKey = now('Africa/Lagos')->format('Y-m-d');
        $stats = [
            'ai_429_today' => (int) Cache::get("quota:stats:429_ai:{$dateKey}", 0),
            'ai_messages_today' => (int) Cache::get("quota:stats:ai:{$dateKey}", 0),
            'activities_today' => (int) Cache::get("quota:stats:activities:{$dateKey}", 0),
        ];

        return $this->sendResponse([
            'global_defaults' => $quotas,
            'plans' => $plans,
            'stats' => $stats,
        ], 'Quota overview.');
    }

    /**
     * Get user-specific quota settings and current usage.
     */
    public function getUserQuota(Request $request, User $user)
    {
        $dateKey = now('Africa/Lagos')->format('Y-m-d');

        // Get current usage from cache
        $aiUsage = (int) Cache::get("quota:ai:{$user->id}:{$dateKey}", 0);
        $activityUsage = (int) Cache::get("quota:activities:{$user->id}:{$dateKey}", 0);

        // Get user's custom quota settings
        $userQuota = [
            'custom_ai_messages' => $user->custom_ai_messages,
            'custom_daily_activities' => $user->custom_daily_activities,
            'grace_period_days' => $user->grace_period_days,
            'has_unlimited_quota' => $user->has_unlimited_quota,
            'quota_override_expires_at' => $user->quota_override_expires_at,
        ];

        // Get global defaults for comparison
        $globalDefaults = Cache::remember(self::CACHE_KEY, 3600, function () {
            $row = QuotaSetting::query()->orderBy('id', 'asc')->first();

            return [
                'free_daily_activities' => $row?->free_daily_activities ?? 2,
                'free_ai_messages' => $row?->free_ai_messages ?? 10,
                'new_user_ai_messages' => $row?->new_user_ai_messages ?? 15,
                'new_user_days' => $row?->new_user_days ?? 7,
                'distress_extension_messages' => $row?->distress_extension_messages ?? 5,
                'abuse_cap_messages' => $row?->abuse_cap_messages ?? 5,
            ];
        });

        return $this->sendResponse([
            'user' => $userQuota,
            'usage' => [
                'ai_messages_today' => $aiUsage,
                'activities_today' => $activityUsage,
            ],
            'global_defaults' => $globalDefaults,
        ], 'User quota retrieved.');
    }

    /**
     * Update user-specific quota settings.
     */
    public function updateUserQuota(Request $request, User $user)
    {
        $data = $request->validate([
            'custom_ai_messages' => 'nullable|integer|min:0',
            'custom_daily_activities' => 'nullable|integer|min:0',
            'grace_period_days' => 'nullable|integer|min:0',
            'has_unlimited_quota' => 'nullable|boolean',
            'quota_override_expires_at' => 'nullable|date',
        ]);

        // Update user quota settings
        $user->update($data);

        // Clear any cached quota data for this user
        $dateKey = now('Africa/Lagos')->format('Y-m-d');
        Cache::forget("quota:ai:{$user->id}:{$dateKey}");
        Cache::forget("quota:activities:{$user->id}:{$dateKey}");

        return $this->sendResponse($user->only([
            'custom_ai_messages',
            'custom_daily_activities',
            'grace_period_days',
            'has_unlimited_quota',
            'quota_override_expires_at',
        ]), 'User quota updated successfully.');
    }

    /**
     * List users with active manual distress/quota overrides.
     */
    public function distressOverrides(Request $request)
    {
        $users = User::query()
            ->where(function ($q) {
                $q->where('has_unlimited_quota', true)
                  ->orWhereNotNull('custom_ai_messages')
                  ->orWhereNotNull('custom_daily_activities')
                  ->orWhereNotNull('quota_override_expires_at');
            })
            ->when($request->search, fn ($q, $s) => $q->where(function ($q2) use ($s) {
                $q2->where('first_name', 'like', "%{$s}%")
                   ->orWhere('last_name', 'like', "%{$s}%")
                   ->orWhere('email', 'like', "%{$s}%");
            }))
            ->select('id', 'first_name', 'last_name', 'email', 'has_unlimited_quota',
                     'custom_ai_messages', 'custom_daily_activities',
                     'grace_period_days', 'quota_override_expires_at')
            ->paginate($request->per_page ?? 20);

        return $this->sendResponse($users, 'Distress overrides retrieved.');
    }

    /**
     * Revoke a user's manual quota override (clear back to defaults).
     */
    public function revokeDistressOverride(User $user)
    {
        $user->update([
            'has_unlimited_quota' => false,
            'custom_ai_messages' => null,
            'custom_daily_activities' => null,
            'quota_override_expires_at' => null,
        ]);

        $dateKey = now('Africa/Lagos')->format('Y-m-d');
        Cache::forget("quota:ai:{$user->id}:{$dateKey}");
        Cache::forget("quota:distress:{$user->id}");

        return $this->sendResponse([], 'Distress override revoked.');
    }

    /**
     * Reset user's daily quota counters.
     */
    public function resetUserQuota(Request $request, User $user)
    {
        $dateKey = now('Africa/Lagos')->format('Y-m-d');

        // Reset quota counters
        Cache::forget("quota:ai:{$user->id}:{$dateKey}");
        Cache::forget("quota:activities:{$user->id}:{$dateKey}");

        return $this->sendResponse([
            'message' => 'User quota reset successfully',
            'user_id' => $user->id,
            'reset_date' => $dateKey,
        ], 'User quota reset.');
    }
}
