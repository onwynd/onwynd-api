<?php

namespace App\Http\Controllers\API\V1\Finance;

use App\Http\Controllers\API\BaseController;
use App\Models\Payment;
use App\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseController
{
    public function index()
    {
        return $this->stats();
    }

    public function stats()
    {
        $currentMonthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();

        // Revenue (from Payments)
        $currentRevenue = Payment::where('status', 'successful')->sum('amount');
        $lastMonthRevenue = Payment::where('status', 'successful')
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('amount');

        $revenueChange = $lastMonthRevenue > 0 ? (($currentRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 0;

        // Expenses (from Payouts)
        $currentExpenses = Payout::where('status', 'completed')->sum('amount');

        // Net Income
        $netIncome = $currentRevenue - $currentExpenses;

        // Pending Payouts
        $pendingPayouts = Payout::where('status', 'pending')->sum('amount');

        $stats = [
            [
                'title' => 'Total Revenue',
                'value' => '₦'.number_format($currentRevenue, 2),
                'change' => number_format(abs($revenueChange), 1).'%',
                'changeType' => $revenueChange >= 0 ? 'increase' : 'decrease',
                'iconName' => 'wallet',
                'description' => 'Total revenue from all sources',
            ],
            [
                'title' => 'Total Expenses',
                'value' => '₦'.number_format($currentExpenses, 2),
                'change' => '0%',
                'changeType' => 'neutral',
                'iconName' => 'activity',
                'description' => 'Total payouts and expenses',
            ],
            [
                'title' => 'Net Income',
                'value' => '₦'.number_format($netIncome, 2),
                'change' => '0%',
                'changeType' => 'increase',
                'iconName' => 'pie-chart',
                'description' => 'Revenue minus expenses',
            ],
            [
                'title' => 'Pending Payouts',
                'value' => '₦'.number_format($pendingPayouts, 2),
                'change' => '0%',
                'changeType' => 'neutral',
                'iconName' => 'credit-card',
                'description' => 'Payouts waiting for processing',
            ],
        ];

        return $this->sendResponse($stats, 'Stats retrieved successfully.');
    }

    public function transactions(Request $request)
    {
        // Union Payment and Payout for a unified transaction list
        $payments = Payment::select(
            DB::raw("CONCAT('pay_', id) as id"),
            'amount',
            DB::raw("CASE WHEN is_reconciled = 1 THEN 'reconciled' ELSE status END as status"),
            'created_at',
            DB::raw("'income' as type"),
            DB::raw("'Payment Received' as description"),
            DB::raw("'General' as category")
        );

        $payouts = Payout::select(
            DB::raw("CONCAT('pout_', id) as id"),
            'amount',
            DB::raw("CASE WHEN is_reconciled = 1 THEN 'reconciled' ELSE status END as status"),
            'created_at',
            DB::raw("'expense' as type"),
            DB::raw("'Payout Processed' as description"),
            DB::raw("'Transfer' as category")
        );

        // Combine and paginate
        $transactions = $payments->union($payouts)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return $this->sendResponse($transactions, 'Transactions retrieved successfully.');
    }

    public function reconcile(Request $request, $id)
    {
        $status = $request->input('status', 'reconciled');
        $isReconciled = $status === 'reconciled';
        $now = now();

        if (str_starts_with($id, 'pay_')) {
            $realId = substr($id, 4);
            $payment = Payment::find($realId);
            if ($payment) {
                $payment->is_reconciled = $isReconciled;
                $payment->reconciled_at = $isReconciled ? $now : null;
                $payment->save();

                return $this->sendResponse($payment, 'Payment reconciled.');
            }
        } elseif (str_starts_with($id, 'pout_')) {
            $realId = substr($id, 5);
            $payout = Payout::find($realId);
            if ($payout) {
                $payout->is_reconciled = $isReconciled;
                $payout->reconciled_at = $isReconciled ? $now : null;
                $payout->save();

                return $this->sendResponse($payout, 'Payout reconciled.');
            }
        }

        return $this->sendError('Transaction not found.', [], 404);
    }

    public function expenses(Request $request)
    {
        // Breakdown of expenses (Payouts by Bank for now)
        $breakdown = Payout::select('bank_name as name', DB::raw('SUM(amount) as value'))
            ->where('status', 'completed')
            ->groupBy('bank_name')
            ->get()
            ->map(function ($item) {
                $item->color = '#'.substr(md5($item->name), 0, 6);

                return $item;
            });

        return $this->sendResponse($breakdown, 'Expense breakdown retrieved successfully.');
    }

    public function pnl(Request $request)
    {
        $month = $request->get('month');
        $start = $month ? now()->setYear((int) substr($month, 0, 4))->setMonth((int) substr($month, 5, 2))->startOfMonth() : now()->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $revenue = Payment::whereIn('status', ['completed', 'successful'])
            ->whereBetween(DB::raw('COALESCE(completed_at, created_at)'), [$start, $end])
            ->sum('amount');

        $expenses = Payout::where('status', 'completed')
            ->whereBetween(DB::raw('COALESCE(processed_at, created_at)'), [$start, $end])
            ->sum('amount');

        $net = $revenue - $expenses;

        return $this->sendResponse([
            'month' => $start->format('Y-m'),
            'revenue' => $revenue,
            'expenses' => $expenses,
            'net_income' => $net,
        ], 'P&L calculated');
    }

    public function cac(Request $request)
    {
        $month = $request->get('month');
        $start = $month ? now()->setYear((int) substr($month, 0, 4))->setMonth((int) substr($month, 5, 2))->startOfMonth() : now()->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $newUsers = Payment::whereIn('status', ['completed', 'successful'])
            ->whereBetween(DB::raw('COALESCE(completed_at, created_at)'), [$start, $end])
            ->pluck('user_id')
            ->unique();

        $existingBefore = Payment::whereIn('status', ['completed', 'successful'])
            ->where('completed_at', '<', $start)
            ->whereIn('user_id', $newUsers)
            ->pluck('user_id')
            ->unique();

        $acquisitions = $newUsers->diff($existingBefore)->count();

        $ambassadorCost = \App\Models\AmbassadorPayout::where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        $cac = $acquisitions > 0 ? $ambassadorCost / $acquisitions : 0.0;

        return $this->sendResponse([
            'month' => $start->format('Y-m'),
            'acquisitions' => $acquisitions,
            'cost' => $ambassadorCost,
            'cac' => round($cac, 2),
        ], 'CAC calculated');
    }

    public function ltv(Request $request)
    {
        $months = (int) $request->get('months', 12);
        if ($months < 1) {
            $months = 12;
        }
        $start = now()->subMonths($months)->startOfMonth();
        $end = now()->endOfMonth();

        $payments = Payment::whereIn('status', ['completed', 'successful'])
            ->where('payment_type', 'subscription')
            ->whereBetween(DB::raw('COALESCE(completed_at, created_at)'), [$start, $end])
            ->get(['user_id', 'amount']);

        $byUser = [];
        foreach ($payments as $p) {
            $uid = $p->user_id;
            if (! isset($byUser[$uid])) {
                $byUser[$uid] = 0.0;
            }
            $byUser[$uid] += (float) $p->amount;
        }

        $payingUsers = count($byUser);
        $totalRevenue = array_sum($byUser);
        $avgLtv = $payingUsers > 0 ? $totalRevenue / $payingUsers : 0.0;

        return $this->sendResponse([
            'months' => $months,
            'paying_users' => $payingUsers,
            'total_revenue' => $totalRevenue,
            'average_ltv' => round($avgLtv, 2),
        ], 'LTV calculated');
    }
}
