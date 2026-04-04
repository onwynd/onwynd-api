<?php

namespace App\Http\Controllers\API\V1\HR;

use App\Http\Controllers\API\BaseController;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends BaseController
{
    public function index(Request $request)
    {
        $totalEmployees = User::whereHas('role', function ($q) {
            $q->where('slug', 'employee');
        })->count();

        $pendingLeave = LeaveRequest::where('status', 'pending')->count();

        $payrollPending = Payroll::where('status', 'pending')->sum('amount');

        return $this->sendResponse([
            'total_employees' => $totalEmployees,
            'pending_leave_requests' => $pendingLeave,
            'pending_payroll_amount' => $payrollPending,
        ], 'HR Dashboard data retrieved.');
    }

    /**
     * Aggregated HR stats (headcount, leave utilisation, recent hires, etc.)
     */
    public function stats(Request $request)
    {
        $totalEmployees = User::whereHas('role', fn ($q) => $q->where('slug', 'employee'))->count();
        $activeEmployees = User::whereHas('role', fn ($q) => $q->where('slug', 'employee'))
            ->where('status', 'active')->count();

        $pendingLeave = LeaveRequest::where('status', 'pending')->count();
        $approvedLeave = LeaveRequest::where('status', 'approved')
            ->whereMonth('start_date', now()->month)->count();

        $newHiresThisMonth = User::whereHas('role', fn ($q) => $q->where('slug', 'employee'))
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $totalPayrollThisMonth = Payroll::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        return $this->sendResponse([
            'total_employees' => $totalEmployees,
            'active_employees' => $activeEmployees,
            'pending_leave' => $pendingLeave,
            'approved_leave_month' => $approvedLeave,
            'new_hires_this_month' => $newHiresThisMonth,
            'total_payroll_month' => $totalPayrollThisMonth,
        ], 'HR stats retrieved.');
    }

    /**
     * Payroll financial flow data for charts (last N months).
     */
    public function financialFlow(Request $request)
    {
        $months = max(1, min(12, (int) $request->input('period', 6)));

        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $total = Payroll::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('amount');

            $data[] = [
                'month' => $date->format('M Y'),
                'amount' => (float) $total,
            ];
        }

        return $this->sendResponse($data, 'HR financial flow retrieved.');
    }
}
