<?php

namespace App\Http\Controllers\API\V1\Institutional;

use App\Http\Controllers\API\BaseController;
use App\Models\Institutional\Organization;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    /**
     * Get institutional dashboard stats
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Admin sees aggregate of all institutions
        if ($user->hasRole('admin')) {
            $stats = [
                'total_organizations' => Organization::count(),
                'total_corporates' => Organization::where('type', 'corporate')->count(),
                'total_universities' => Organization::where('type', 'university')->count(),
                'total_members' => Organization::withCount('members')->get()->sum('members_count'),
            ];

            return $this->sendResponse($stats, 'Admin institutional dashboard.');
        }

        // Institutional Admin sees their organization stats
        $orgMember = $user->organizationMemberships()
            ->whereIn('role', ['admin', 'manager'])
            ->first();

        if (! $orgMember) {
            return $this->sendError('No institutional admin access found.', [], 403);
        }

        $org = $orgMember->organization;

        // Calculate stats for this org
        $memberIds = $org->members()->pluck('user_id');
        $activeMembers = User::whereIn('id', $memberIds)
            ->where('last_activity_at', '>', now()->subDays(30))
            ->count();

        // Calculate session usage
        $totalSessionsUsed = $org->members()->sum('sessions_used_this_month');
        $totalSessionLimit = $org->members()->sum('sessions_limit');

        // Calculate contract days remaining (if applicable)
        // Assuming there's a contract_end_date on Organization or Dashboard model
        // For now, checking if Organization model has it (it doesn't in fillable, but maybe in migration?)
        // Let's assume infinite for now or use subscription status.

        $stats = [
            'organization' => $org->name,
            'type' => $org->type,
            'status' => $org->status,
            'members' => [
                'total' => $memberIds->count(),
                'active' => $activeMembers,
                'max' => $org->max_members,
                'utilization_rate' => $org->max_members > 0
                    ? round(($memberIds->count() / $org->max_members) * 100, 1)
                    : 0,
            ],
            'sessions' => [
                'used_this_month' => $totalSessionsUsed,
                'total_limit_month' => $totalSessionLimit,
                'utilization_rate' => $totalSessionLimit > 0
                    ? round(($totalSessionsUsed / $totalSessionLimit) * 100, 1)
                    : 0,
            ],
            'paywall_code' => $org->status === 'suspended' || $org->status === 'payment_failed' ? 402 : null,
        ];

        return $this->sendResponse($stats, 'Institutional dashboard retrieved.');
    }
}
