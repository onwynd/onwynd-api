<?php

namespace App\Http\Controllers\API\V1\Finance;

use App\Http\Controllers\API\BaseController;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Payout; // Assuming existing Payment model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueController extends BaseController
{
    public function index(Request $request)
    {
        $period = $request->query('period', 'monthly'); // monthly, yearly
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Base query for successful payments
        $baseQuery = Payment::where('status', 'successful');

        if ($startDate && $endDate) {
            $baseQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Stats aggregation
        $totalRevenue = (clone $baseQuery)->sum('amount');
        $totalPayouts = Payout::where('status', 'completed')
            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->sum('amount');
        $pendingInvoices = Invoice::where('status', 'pending')
            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->sum('amount');

        $netIncome = $totalRevenue - $totalPayouts;

        $stats = [
            'total_revenue' => $totalRevenue,
            'total_payouts' => $totalPayouts,
            'pending_invoices' => $pendingInvoices,
            'net_income' => $netIncome,
        ];

        // Chart data aggregation
        $revenueChartQuery = clone $baseQuery;
        $expensesChartQuery = Payout::where('status', 'completed');

        if ($startDate && $endDate) {
            $expensesChartQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        if ($period === 'yearly') {
            $revenueChartQuery->select(
                DB::raw('sum(amount) as total'),
                DB::raw("DATE_FORMAT(created_at, '%Y') as date")
            )->groupBy('date')->orderBy('date', 'asc');

            $expensesChartQuery->select(
                DB::raw('sum(amount) as total'),
                DB::raw("DATE_FORMAT(created_at, '%Y') as date")
            )->groupBy('date')->orderBy('date', 'asc');
        } else {
            // Default to monthly
            $revenueChartQuery->select(
                DB::raw('sum(amount) as total'),
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as date")
            )->groupBy('date')->orderBy('date', 'asc');

            $expensesChartQuery->select(
                DB::raw('sum(amount) as total'),
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as date")
            )->groupBy('date')->orderBy('date', 'asc');
        }

        // Limit to last 12 periods if no date range is specified
        if (! $startDate && ! $endDate) {
            $revenueChartQuery->limit(12);
            $expensesChartQuery->limit(12);
        }

        $revenueData = $revenueChartQuery->get()->keyBy('date');
        $expensesData = $expensesChartQuery->get()->keyBy('date');

        // Merge dates
        $allDates = $revenueData->keys()->merge($expensesData->keys())->unique()->sort();

        $chartData = $allDates->map(function ($date) use ($revenueData, $expensesData) {
            return [
                'date' => $date,
                'revenue' => $revenueData->has($date) ? $revenueData[$date]->total : 0,
                'expenses' => $expensesData->has($date) ? $expensesData[$date]->total : 0,
            ];
        })->values();

        // Booking fee revenue breakdown
        $bookingFeeRevenue = DB::table('therapy_sessions')
            ->where('status', 'completed')
            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('ended_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            })
            ->sum(DB::raw('COALESCE(booking_fee_amount, 0)'));

        $commissionRevenue = DB::table('therapy_sessions')
            ->where('status', 'completed')
            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('ended_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            })
            ->sum(DB::raw('session_rate - COALESCE(commission_amount, 0)'));

        $stats['booking_fee_revenue'] = (float) $bookingFeeRevenue;
        $stats['commission_revenue']  = (float) $commissionRevenue;

        return $this->sendResponse([
            'stats' => $stats,
            'chart_data' => $chartData,
        ], 'Revenue data retrieved successfully.');
    }
}
