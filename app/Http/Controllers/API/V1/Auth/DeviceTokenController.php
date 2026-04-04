<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceTokenController extends Controller
{
    /**
     * Register or refresh an FCM device token for the authenticated user.
     * Uses upsert so duplicate tokens are never created — only updated.
     *
     * POST /api/v1/auth/device-token
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|max:512',
            'platform' => 'nullable|string|in:web,android,ios',
        ]);

        $user = Auth::user();
        $token = $request->input('token');

        // Upsert: if the token already exists for any user, reactivate and reassign
        // to the current user (handles device handoff). Otherwise create fresh.
        $deviceToken = DeviceToken::updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $user->id,
                'platform' => $request->input('platform', 'web'),
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Device token registered',
            'data' => [
                'id' => $deviceToken->id,
                'platform' => $deviceToken->platform,
                'is_active' => $deviceToken->is_active,
            ],
        ], 200);
    }

    /**
     * Deactivate an FCM device token on logout or app uninstall.
     * Soft-deactivates — does not hard-delete so audit trail is preserved.
     *
     * DELETE /api/v1/auth/device-token?token=...
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|max:512',
        ]);

        $user = Auth::user();
        $token = $request->query('token') ?? $request->input('token');

        $updated = DeviceToken::where('token', $token)
            ->where('user_id', $user->id)
            ->update([
                'is_active' => false,
                'last_used_at' => now(),
            ]);

        if (! $updated) {
            return response()->json([
                'success' => false,
                'message' => 'Token not found or not owned by this user.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Device token deactivated',
        ], 200);
    }
}
