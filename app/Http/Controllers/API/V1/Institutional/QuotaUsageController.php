<?php

namespace App\Http\Controllers\API\V1\Institutional;

use App\Http\Controllers\API\BaseController;
use App\Models\Institutional\OrganizationMember;
use Illuminate\Http\Request;

class QuotaUsageController extends BaseController
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            // Get the organization this admin manages
            $orgMember = OrganizationMember::where('user_id', $user->id)
                ->whereIn('role', ['admin', 'manager'])
                ->first();

            if (! $orgMember) {
                return $this->sendError('Organization not found or access denied.', [], 404);
            }

            $orgId = $orgMember->organization_id;

            // Fetch members with usage stats
            // We use leftJoin to get user details
            $seats = OrganizationMember::where('organization_id', $orgId)
                ->join('users', 'organization_members.user_id', '=', 'users.id')
                ->select([
                    'organization_members.id as seat_id',
                    'organization_members.user_id',
                    'organization_members.role',
                    'organization_members.status',
                    'organization_members.sessions_used_this_month',
                    'organization_members.sessions_limit',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'users.last_activity_at',
                ])
                ->get();

            // Calculate aggregated stats
            $totalSeats = $seats->count();
            $activeSeats = $seats->where('status', 'active')->count();

            // "Zero Activity" defined as no sessions used this month AND inactive for > 30 days
            $zeroActivity = $seats->filter(function ($s) {
                $inactive = $s->last_activity_at ? now()->diffInDays($s->last_activity_at) > 30 : true;

                return $s->sessions_used_this_month == 0 && $inactive;
            })->count();

            // "At Risk" defined as high usage (> 80% of limit) or very low usage despite being "active" status
            $atRiskUsers = $seats->filter(function ($s) {
                if ($s->sessions_limit > 0) {
                    $usageRate = $s->sessions_used_this_month / $s->sessions_limit;

                    return $usageRate >= 0.8; // High usage risk (burnout?)
                }

                return false;
            })->values();

            $seatData = $seats->map(function ($s) {
                return [
                    'seat_id' => $s->seat_id,
                    'user_id' => $s->user_id,
                    'name' => $s->first_name.' '.$s->last_name,
                    'email' => $s->email,
                    'role' => $s->role,
                    'status' => $s->status,
                    'sessions_used' => $s->sessions_used_this_month,
                    'sessions_limit' => $s->sessions_limit,
                    'last_active' => $s->last_activity_at,
                    'usage_percentage' => $s->sessions_limit > 0 ? round(($s->sessions_used_this_month / $s->sessions_limit) * 100) : 0,
                ];
            });

            return $this->sendResponse([
                'summary' => [
                    'total_seats' => $totalSeats,
                    'active_seats' => $activeSeats,
                    'zero_activity' => $zeroActivity,
                    'high_usage_risk' => $atRiskUsers->count(),
                ],
                'seats' => $seatData,
            ], 'Quota usage retrieved successfully.');

        } catch (\Throwable $e) {
            return $this->sendError('Failed to retrieve quota usage.', ['error' => $e->getMessage()], 500);
        }
    }
}
