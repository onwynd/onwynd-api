<?php

namespace App\Http\Controllers\API\V1\Analytics;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MetricsController extends BaseController
{
    /**
     * Track PWA installation
     *
     * @OA\Post(
     *     path="/api/analytics/pwa-install",
     *     operationId="trackPWAInstall",
     *     tags={"Analytics"},
     *     summary="Track PWA installation",
     *     description="Records when a user installs the PWA on their device",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="timestamp", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *             @OA\Property(property="userAgent", type="string", example="Mozilla/5.0..."),
     *             @OA\Property(property="platform", type="string", example="Win32"),
     *             @OA\Property(property="language", type="string", example="en-US"),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Installation tracked successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="PWA install tracked successfully"),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to track PWA install"),
     *         )
     *     )
     * )
     */
    public function trackPWAInstall(Request $request)
    {
        try {
            $validated = $request->validate([
                'timestamp' => 'required|date',
                'userAgent' => 'required|string',
                'platform' => 'required|string',
                'language' => 'required|string',
            ]);

            // Create PWA install record
            \App\Models\PWAInstall::create([
                'installed_at' => $validated['timestamp'],
                'user_agent' => $validated['userAgent'],
                'platform' => $validated['platform'],
                'language' => $validated['language'],
                'ip_address' => $request->ip(),
            ]);

            Log::info('PWA install tracked', [
                'timestamp' => $validated['timestamp'],
                'platform' => $validated['platform'],
                'language' => $validated['language'],
            ]);

            return $this->sendResponse([], 'PWA install tracked successfully');
        } catch (\Exception $e) {
            Log::error('Failed to track PWA install', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return $this->sendError('Failed to track PWA install', [], 500);
        }
    }
}
