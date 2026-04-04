<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Mail\WeeklyAdminReport;
use App\Models\Payment;
use App\Models\User;
use App\Services\Reporting\AdminReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReportController extends BaseController
{
    protected $reportService;

    public function __construct(AdminReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Get Weekly Dashboard Data
     */
    public function weeklyDashboard(Request $request)
    {
        try {
            $metrics = $this->reportService->getWeeklyMetrics();
            $aiAnalysis = $this->reportService->getAiAnalysis($metrics);
            $forecast = $this->reportService->getForecast();
            $actionSteps = $this->reportService->getActionSteps();

            $data = [
                'metrics' => $metrics,
                'ai_analysis' => $aiAnalysis,
                'forecast' => $forecast,
                'action_steps' => $actionSteps,
                'period' => [
                    'start' => now()->startOfWeek()->toFormattedDateString(),
                    'end' => now()->endOfWeek()->toFormattedDateString(),
                ],
            ];

            return $this->sendResponse($data, 'Weekly dashboard data retrieved.');
        } catch (\Exception $e) {
            Log::error('Dashboard Report Error: '.$e->getMessage());

            return $this->sendError('Failed to generate report', [], 500);
        }
    }

    /**
     * Trigger Weekly Email Report
     */
    public function sendWeeklyEmail(Request $request)
    {
        try {
            $user = $request->user();
            // Ensure user is admin
            if (! $user) { // Add role check here in production
                return $this->sendError('Unauthorized', [], 403);
            }

            $metrics = $this->reportService->getWeeklyMetrics();
            $aiAnalysis = $this->reportService->getAiAnalysis($metrics);
            $forecast = $this->reportService->getForecast();
            $actionSteps = $this->reportService->getActionSteps();

            Mail::to($user->email)->send(new WeeklyAdminReport(
                now()->startOfWeek()->toFormattedDateString(),
                now()->endOfWeek()->toFormattedDateString(),
                $metrics,
                $aiAnalysis,
                $forecast,
                $actionSteps
            ));

            return $this->sendResponse([], 'Weekly report email sent.');
        } catch (\Exception $e) {
            Log::error('Email Report Error: '.$e->getMessage());

            return $this->sendError('Failed to send email', [], 500);
        }
    }

    /**
     * Import Documents (Stub)
     */
    public function importDocuments(Request $request)
    {
        // Validation would go here
        if (! $request->hasFile('document')) {
            return $this->sendError('No file uploaded', [], 400);
        }

        // Logic to process/store document
        // $path = $request->file('document')->store('documents');

        return $this->sendResponse([], 'Document imported successfully (stub).');
    }

    /**
     * Export Report (Stub)
     */
    public function exportReport(Request $request)
    {
        // Logic to generate CSV/PDF
        return $this->sendResponse(['download_url' => url('/storage/reports/report.pdf')], 'Report exported.');
    }

    public function financial(Request $request)
    {
        $startDate = $request->input('start_date', now()->subMonth());
        $endDate = $request->input('end_date', now());

        $revenue = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('date')
            ->get();

        return $this->sendResponse($revenue, 'Financial report retrieved.');
    }

    public function userGrowth(Request $request)
    {
        $startDate = $request->input('start_date', now()->subMonth());
        $endDate = $request->input('end_date', now());

        $growth = User::whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->get();

        return $this->sendResponse($growth, 'User growth report retrieved.');
    }
}
