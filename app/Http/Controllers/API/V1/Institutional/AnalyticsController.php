<?php

namespace App\Http\Controllers\API\V1\Institutional;

use App\Http\Controllers\API\BaseController;
use App\Services\Institutional\InstitutionalAnalyticsService;
use Illuminate\Http\Request;

class AnalyticsController extends BaseController
{
    private InstitutionalAnalyticsService $service;

    public function __construct()
    {
        $this->service = new InstitutionalAnalyticsService;
    }

    public function engagement(Request $request, int $organizationId)
    {
        $request->validate([
            'period' => 'nullable|in:7d,30d,90d',
        ]);

        $period = $request->get('period', '30d');
        $data = $this->service->engagementMetrics($organizationId, $period);

        return $this->sendResponse($data, 'Engagement metrics');
    }

    public function atRisk(Request $request, int $organizationId)
    {
        $data = $this->service->atRiskUsers($organizationId);

        return $this->sendResponse($data, 'At-risk users');
    }

    public function monthlyReport(Request $request, int $organizationId)
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $month = $request->get('month');
        $data = $this->service->monthlyReport($organizationId, $month);

        return $this->sendResponse($data, 'Monthly report');
    }
}
