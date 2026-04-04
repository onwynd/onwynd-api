<?php

namespace App\Http\Controllers\API\V1\Health;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    /**
     * Personal dashboard summary stats for the authenticated health personnel.
     */
    public function dashboard(Request $request)
    {
        return $this->sendResponse([
            'my_checkins_today' => 0,
            'reports_submitted' => 0,
            'pending_reports' => 0,
            'active_distress_cases' => 0,
        ], 'Health personnel dashboard retrieved.');
    }

    /**
     * Legacy stats endpoint  kept for backward compatibility.
     */
    public function stats(Request $request)
    {
        return $this->sendResponse([
            'total_check_ins' => 0,
            'pending_reviews' => 0,
            'active_patients' => 0,
            'documents_pending' => 0,
        ], 'Health stats retrieved.');
    }

    /**
     * Check-ins performed by the authenticated health personnel (today-scoped by default).
     */
    public function myCheckIns(Request $request)
    {
        return $this->sendResponse([], 'My check-ins retrieved.');
    }

    /**
     * Reports submitted by the authenticated health personnel.
     */
    public function myReports(Request $request)
    {
        return $this->sendResponse([], 'My reports retrieved.');
    }

    /**
     * Legacy check-ins endpoint  kept for backward compatibility.
     */
    public function checkIns(Request $request)
    {
        return $this->sendResponse([], 'Health check-ins retrieved.');
    }

    /**
     * Legacy documents endpoint  kept for backward compatibility.
     */
    public function documents(Request $request)
    {
        return $this->sendResponse([], 'Health documents retrieved.');
    }

    /**
     * Chart data for the health personnel activity chart.
     * Accepts ?period=day|week|month
     */
    public function chartData(Request $request)
    {
        return $this->sendResponse([], 'Health chart data retrieved.');
    }
}
