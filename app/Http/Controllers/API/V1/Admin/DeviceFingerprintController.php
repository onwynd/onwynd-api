<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DeviceFingerprintController extends BaseController
{
    /**
     * GET /api/v1/admin/security/devices
     * Returns users with cached device fingerprints for admin review.
     */
    public function index(): JsonResponse
    {
        $users = User::select('id', 'first_name', 'last_name', 'email', 'last_seen_at', 'role_id')
            ->with(['role:id,name'])
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', now()->subDays(30))
            ->orderByDesc('last_seen_at')
            ->limit(200)
            ->get();

        $devices = [];
        $multiDeviceUsers = 0;

        foreach ($users as $user) {
            $cacheKey = "device_fingerprint_{$user->id}";
            $fingerprints = Cache::get($cacheKey, []);

            if (! is_array($fingerprints) || count($fingerprints) === 0) {
                continue;
            }

            $deviceCount = count($fingerprints);
            if ($deviceCount > 1) {
                $multiDeviceUsers++;
            }

            $online = (bool) Cache::get('user-is-online-'.$user->id, false);

            $devices[] = [
                'user_id' => $user->id,
                'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                'email' => $user->email,
                'role' => $user->role->name ?? null,
                'device_count' => $deviceCount,
                'fingerprints' => array_map(static function ($fp) {
                    return is_string($fp) ? substr($fp, 0, 12) : '';
                }, $fingerprints),
                'last_seen_at' => $user->last_seen_at ? $user->last_seen_at->toIso8601String() : null,
                'online' => $online,
            ];
        }

        return $this->sendResponse([
            'devices' => $devices,
            'summary' => [
                'total_users' => count($devices),
                'multi_device_users' => $multiDeviceUsers,
            ],
        ], 'Device fingerprints retrieved successfully.');
    }
}
