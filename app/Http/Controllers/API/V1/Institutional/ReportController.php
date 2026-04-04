<?php

namespace App\Http\Controllers\API\V1\Institutional;

use App\Http\Controllers\API\BaseController;
use App\Models\Institutional\Organization;
use App\Services\Institutional\InstitutionalAnalyticsService;
use Illuminate\Http\Request;

class ReportController extends BaseController
{
    public function __construct(private readonly InstitutionalAnalyticsService $analyticsService)
    {
    }

    public function index(Request $request)
    {
        $request->validate([
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'period' => 'nullable|in:7d,30d,90d',
            'month' => 'nullable|date_format:Y-m',
        ]);

        $user = $request->user();

        $organizationQuery = Organization::query();
        if ($request->filled('organization_id')) {
            $organizationQuery->whereKey($request->integer('organization_id'));
        }

        if (! $user->hasRole(['admin', 'super_admin', 'founder', 'ceo', 'coo', 'sales', 'relationship_manager'])) {
            $organizationQuery->whereHas('members', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            });
        } elseif ($user->hasRole('relationship_manager')) {
            $organizationQuery->where('relationship_manager_id', $user->id);
        }

        $organization = $organizationQuery->orderBy('name')->first();
        if (! $organization) {
            return $this->sendError('No accessible organization found.', [], 404);
        }

        $period = $request->string('period')->toString() ?: '30d';
        $month = $request->string('month')->toString() ?: now()->format('Y-m');

        $engagement = $this->analyticsService->engagementMetrics($organization->id, $period);
        $monthly = $this->analyticsService->monthlyReport($organization->id, $month);
        $atRisk = $this->analyticsService->atRiskUsers($organization->id);

        return $this->sendResponse([
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'type' => $organization->type,
            ],
            'engagement' => $engagement,
            'monthly' => $monthly,
            'at_risk' => $atRisk,
        ], 'Institutional reports retrieved.');
    }
}