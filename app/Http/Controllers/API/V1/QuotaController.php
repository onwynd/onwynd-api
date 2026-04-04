<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Services\Quota\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuotaController extends Controller
{
    private $quotaService;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->quotaService = app()->bound(QuotaService::class)
            ? app()->make(QuotaService::class)
            : null;
    }

    /**
     * Get current quota status for authenticated user or anonymous session
     */
    public function getQuotaStatus(Request $request): JsonResponse
    {
        try {
            if (! $this->quotaService) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quota service not available',
                ], 503);
            }

            $user = Auth::user();
            $anonymousId = $request->input('anonymous_id', session()->getId());

            $quotaInfo = $user
                ? $this->quotaService->getQuotaInfo($user)
                : $this->quotaService->getQuotaInfo(null, $anonymousId);

            return response()->json([
                'success' => true,
                'data' => $quotaInfo,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get quota status: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get quota usage history for authenticated user
     */
    public function getUsageHistory(Request $request): JsonResponse
    {
        try {
            if (! $this->quotaService) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quota service not available',
                ], 503);
            }

            $user = Auth::user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $days = (int) $request->input('days', 30);
            $days = max(1, min($days, 90)); // Limit between 1 and 90 days

            $usageHistory = $this->quotaService->getUsageHistory($user, $days);

            return response()->json([
                'success' => true,
                'data' => [
                    'period_days' => $days,
                    'history' => $usageHistory,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get usage history: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if user can book a session (quota availability check)
     */
    public function canBookSession(Request $request): JsonResponse
    {
        try {
            if (! $this->quotaService) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quota service not available',
                ], 503);
            }

            $user = Auth::user();
            $anonymousId = $request->input('anonymous_id', session()->getId());

            $quotaInfo = $user
                ? $this->quotaService->getQuotaInfo($user)
                : $this->quotaService->getQuotaInfo(null, $anonymousId);

            // Check if user can book based on quota
            $hasCriticalWarnings = false;
            if (! empty($quotaInfo['warnings'])) {
                $criticalWarnings = array_filter($quotaInfo['warnings'], function ($warning) {
                    return $warning['level'] === 'critical';
                });
                $hasCriticalWarnings = ! empty($criticalWarnings);
            }

            // Calculate remaining quota across all periods
            $totalRemaining = 0;
            if (isset($quotaInfo['remaining'])) {
                foreach ($quotaInfo['remaining'] as $period => $remaining) {
                    if ($remaining > 0) {
                        $totalRemaining += $remaining;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'can_book' => ! $hasCriticalWarnings,
                    'quota_info' => $quotaInfo,
                    'total_remaining' => $totalRemaining,
                    'message' => $hasCriticalWarnings
                        ? 'Quota limit exceeded. Please upgrade your plan or wait for quota reset.'
                        : 'You can book sessions within your quota limits.',
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check booking eligibility: '.$e->getMessage(),
            ], 500);
        }
    }
}
