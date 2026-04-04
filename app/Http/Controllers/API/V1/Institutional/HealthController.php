<?php

namespace App\Http\Controllers\API\V1\Institutional;

use App\Http\Controllers\API\BaseController;
use App\Models\Institutional\Organization;
use Illuminate\Http\Request;

class HealthController extends BaseController
{
    /**
     * GET /api/v1/institutional/health-overview
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Access control: Admin, CEO, COO, Sales, Relationship Manager
        if (! $user->hasRole(['admin', 'ceo', 'coo', 'sales', 'relationship_manager'])) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $query = Organization::query();

        // Scope by Relationship Manager
        if ($user->hasRole('relationship_manager')) {
            $query->where('relationship_manager_id', $user->id);
        }

        // Optional filtering for admins
        if ($request->has('manager_id') && $user->hasRole(['admin', 'ceo', 'coo'])) {
            $query->where('relationship_manager_id', $request->manager_id);
        }

        // Get organizations with member counts
        $orgs = $query->withCount('members')->get();

        $active = $orgs->where('status', 'active')->count();

        // Utilization Risk: < 40% utilization
        $atRisk = $orgs->filter(function ($org) {
            if ($org->max_members <= 0) {
                return false;
            }
            $utilization = ($org->members_count / $org->max_members) * 100;

            return $utilization < 40;
        })->count();

        // Renewing in 30 days
        // Assuming 'created_at' + 1 year is renewal date for now, as no subscription_ends_at column
        $renewingSoon = $orgs->filter(function ($org) {
            $renewalDate = $org->created_at->addYear(); // Placeholder logic

            return $renewalDate->diffInDays(now()) <= 30 && $renewalDate->isFuture();
        })->count();

        // Total ARR
        // Placeholder pricing logic based on plan name
        $totalArr = $orgs->sum(function ($org) {
            // Example pricing
            switch ($org->subscription_plan) {
                case 'enterprise': return 5000000;
                case 'business': return 1000000;
                case 'starter': return 250000;
                default: return 0;
            }
        });

        return $this->sendResponse([
            'active_accounts' => $active,
            'at_risk_count' => $atRisk,
            'renewing_soon_count' => $renewingSoon,
            'total_arr' => $totalArr,
        ], 'Health overview retrieved.');
    }
}
