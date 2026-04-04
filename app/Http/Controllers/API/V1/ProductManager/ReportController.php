<?php

namespace App\Http\Controllers\API\V1\ProductManager;

use App\Http\Controllers\API\BaseController;
use App\Models\MaintenanceSchedule;
use App\Models\ProductFeature;
use Illuminate\Http\Request;
use PDF; // Assuming a PDF wrapper exists or we generate CSV

class ReportController extends BaseController
{
    /**
     * List available reports.
     */
    public function index()
    {
        $reports = [
            [
                'id' => 'feature_status',
                'name' => 'Feature Status Report',
                'description' => 'Breakdown of all features by status and priority.',
                'format' => ['csv', 'json'],
            ],
            [
                'id' => 'maintenance_log',
                'name' => 'Maintenance Log',
                'description' => 'History of maintenance schedules and outcomes.',
                'format' => ['csv', 'json'],
            ],
            [
                'id' => 'roadmap_summary',
                'name' => 'Roadmap Summary',
                'description' => 'Executive summary of product roadmap for upcoming quarters.',
                'format' => ['json'],
            ],
        ];

        return $this->sendResponse($reports, 'Available reports retrieved.');
    }

    /**
     * Generate a specific report.
     */
    public function generate(Request $request, $reportId)
    {
        $data = [];

        switch ($reportId) {
            case 'feature_status':
                $data = ProductFeature::with(['requester', 'assignee'])->get();
                break;

            case 'maintenance_log':
                $data = MaintenanceSchedule::with(['requester', 'approver'])->get();
                break;

            case 'roadmap_summary':
                $data = ProductFeature::whereNotNull('quarter')
                    ->orderBy('quarter')
                    ->get()
                    ->groupBy('quarter');
                break;

            default:
                return $this->sendError('Report type not supported.');
        }

        if ($request->input('format') === 'csv') {
            // Logic to convert $data to CSV download
            // For now returning JSON as fallback or implementation detail
            return $this->sendResponse($data, 'Report generated.');
        }

        return $this->sendResponse($data, 'Report generated successfully.');
    }
}
