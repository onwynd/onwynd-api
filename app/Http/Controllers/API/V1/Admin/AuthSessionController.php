<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthSessionController extends BaseController
{
    /**
     * GET /api/v1/admin/auth-sessions
     * Active Sanctum tokens (last used within 24 h).
     */
    public function index(Request $request): JsonResponse
    {
        $query = PersonalAccessToken::with('tokenable:id,first_name,last_name,email,last_seen_at,profile_photo')
            ->where('tokenable_type', User::class)
            ->where(function ($q) {
                $q->where('last_used_at', '>=', now()->subHours(24))
                  ->orWhere(function ($i) {
                      $i->whereNull('last_used_at')
                        ->where('created_at', '>=', now()->subHour());
                  });
            })
            ->orderBy('last_used_at', 'desc')
            ->orderBy('created_at', 'desc');

        $perPage = min((int) ($request->get('per_page', 50)), 200);
        $paginated = $query->paginate($perPage);

        $paginated->getCollection()->transform(function (PersonalAccessToken $token) {
            $user = $token->tokenable;
            return [
                'id'            => $token->id,
                'token_name'    => $token->name,
                'created_at'    => $token->created_at,
                'last_used_at'  => $token->last_used_at,
                'user_id'       => $user?->id,
                'user_name'     => $user ? trim("{$user->first_name} {$user->last_name}") : 'Unknown',
                'user_email'    => $user?->email,
                'last_seen_at'  => $user?->last_seen_at,
                'is_online'     => $user?->last_seen_at
                    ? now()->diffInMinutes($user->last_seen_at) <= 5
                    : false,
            ];
        });

        return $this->sendResponse($paginated, 'Active sessions retrieved.');
    }

    /**
     * DELETE /api/v1/admin/auth-sessions/{id}
     * Revoke a specific token.
     */
    public function destroy(int $id): JsonResponse
    {
        $token = PersonalAccessToken::find($id);
        if (! $token) {
            return $this->sendError('Session not found.');
        }
        $token->delete();

        return $this->sendResponse([], 'Session revoked.');
    }

    /**
     * DELETE /api/v1/admin/auth-sessions/user/{userId}
     * Force-logout: revoke ALL tokens for a user.
     */
    public function revokeUser(int $userId): JsonResponse
    {
        $user = User::find($userId);
        if (! $user) {
            return $this->sendError('User not found.');
        }

        $count = PersonalAccessToken::where('tokenable_id', $userId)
            ->where('tokenable_type', User::class)
            ->delete();

        return $this->sendResponse(['revoked' => $count], "All sessions revoked for user #{$userId}.");
    }
}
