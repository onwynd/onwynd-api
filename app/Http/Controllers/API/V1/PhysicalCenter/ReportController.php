<?php

namespace App\Http\Controllers\API\V1\PhysicalCenter;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;

class ReportController extends BaseController
{
    public function index(Request $request)
    {
        // Mock reports data
        $reports = [
            'daily_footfall' => [
                'date' => now()->toDateString(),
                'count' => 45,
            ],
            'revenue_summary' => [
                'total' => 150000,
                'breakdown' => [
                    'consultations' => 100000,
                    'pharmacy' => 50000,
                ],
            ],
            'inventory_status' => [
                'low_stock_items' => 3,
                'out_of_stock_items' => 1,
            ],
        ];

        return $this->sendResponse($reports, 'Center reports retrieved successfully.');
    }
}
