<?php

namespace App\Http\Controllers\API\V1\Partner;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    public function stats(Request $request)
    {
        return $this->sendResponse([
            'total_employees' => 0,
            'active_contracts' => 0,
            'pending_invoices' => 0,
            'revenue_month' => 0,
        ], 'Partner stats retrieved.');
    }

    public function employees(Request $request)
    {
        return $this->sendResponse([], 'Partner employees retrieved.');
    }

    public function financialFlow(Request $request)
    {
        return $this->sendResponse([], 'Partner financial flow retrieved.');
    }
}
