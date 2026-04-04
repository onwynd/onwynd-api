<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\PhysicalCenter;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MapController extends Controller
{
    /**
     * GET /api/v1/map/data
     * Returns all map pin data:
     *  - Physical centers (lat/lng)
     *  - Active sales agent positions (updated in last 24h)
     *  - Verified therapist positions (pulled from their profile location)
     */
    public function data(): JsonResponse
    {
        // Physical centers
        $centers = PhysicalCenter::where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['uuid', 'name', 'address_line1', 'city', 'state', 'country', 'phone', 'email', 'latitude', 'longitude'])
            ->map(fn ($c) => array_merge($c->toArray(), ['pin_type' => 'center']));

        // Sales agents with known positions (updated in last 24h)
        $agents = UserProfile::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('location_updated_at', '>=', now()->subDay())
            ->whereHas('user', fn ($q) => $q->whereHas('role', fn ($r) => $r->where('slug', 'sales')))
            ->with('user:id,first_name,last_name')
            ->get(['user_id', 'city', 'state', 'latitude', 'longitude', 'location_updated_at'])
            ->map(fn ($p) => [
                'user_id' => $p->user_id,
                'name' => trim($p->user?->first_name.' '.$p->user?->last_name),
                'city' => $p->city,
                'state' => $p->state,
                'latitude' => $p->latitude,
                'longitude' => $p->longitude,
                'location_updated_at' => $p->location_updated_at,
                'pin_type' => 'agent',
            ]);

        // Verified therapists — only those who have lat/lng on their user profile
        $therapists = UserProfile::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereHas('user', fn ($q) => $q->whereHas('therapistProfile', fn ($tp) => $tp->where('is_verified', true)->where('is_accepting_clients', true)))
            ->with(['user:id,first_name,last_name', 'user.therapistProfile:user_id,specializations,hourly_rate,currency,rating_average,total_sessions'])
            ->get(['user_id', 'city', 'state', 'latitude', 'longitude'])
            ->map(fn ($p) => [
                'user_id' => $p->user_id,
                'name' => trim($p->user?->first_name.' '.$p->user?->last_name),
                'city' => $p->city,
                'state' => $p->state,
                'latitude' => $p->latitude,
                'longitude' => $p->longitude,
                'specializations' => $p->user?->therapistProfile?->specializations ?? [],
                'rating' => $p->user?->therapistProfile?->rating_average,
                'hourly_rate' => $p->user?->therapistProfile?->hourly_rate,
                'currency' => $p->user?->therapistProfile?->currency,
                'pin_type' => 'therapist',
            ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'centers' => $centers,
                'agents' => $agents,
                'therapists' => $therapists,
            ],
        ]);
    }

    /**
     * GET /api/v1/map/active-users
     * Returns real-time active user stats (admin only).
     */
    public function activeUsers(): JsonResponse
    {
        // Users with cache-based online presence (< 5 min)
        $onlineCount = Cache::many(
            User::where('last_seen_at', '>=', now()->subMinutes(5))
                ->pluck('id')
                ->map(fn ($id) => 'user-is-online-'.$id)
                ->toArray()
        );
        $onlineNow = collect($onlineCount)->filter()->count();

        // Active in last 5 min (DB-level)
        $activeIn5Min = User::where('last_seen_at', '>=', now()->subMinutes(5))->count();
        // Active in last 15 min
        $activeIn15Min = User::where('last_seen_at', '>=', now()->subMinutes(15))->count();
        // Active today
        $activeToday = User::whereDate('last_seen_at', today())->count();

        // Breakdown by role (today)
        $byRole = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->whereDate('users.last_seen_at', today())
            ->selectRaw('roles.name as role, COUNT(*) as count')
            ->groupBy('roles.name')
            ->orderByDesc('count')
            ->get();

        // Recent activity feed (last 20 users seen)
        $recentActivity = User::select('id', 'first_name', 'last_name', 'last_seen_at')
            ->with('role:id,name')
            ->whereNotNull('last_seen_at')
            ->orderByDesc('last_seen_at')
            ->limit(20)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'online_now' => $onlineNow,
                'active_5min' => $activeIn5Min,
                'active_15min' => $activeIn15Min,
                'active_today' => $activeToday,
                'total_users' => User::count(),
                'by_role' => $byRole,
                'recent_activity' => $recentActivity,
            ],
        ]);
    }

    /**
     * POST /api/v1/map/agent/location
     * Authenticated sales agent or therapist updates their current position.
     */
    public function updateAgentLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $user = $request->user();
        $profile = UserProfile::firstOrCreate(['user_id' => $user->id]);

        $profile->update([
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'location_updated_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Location updated.']);
    }
}
