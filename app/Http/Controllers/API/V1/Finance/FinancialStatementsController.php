<?php

namespace App\Http\Controllers\API\V1\Finance;

use App\Http\Controllers\API\BaseController;
use App\Models\EmployeeSalary;
use App\Models\Payment;
use App\Models\Payout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Financial Statements Controller
 * Serves the Three Pillars of Finance:
 *  - Balance Sheet   (Assets / Liabilities / Equity)
 *  - Income Statement (Revenue → EBITDA → Net Income)
 *  - Cash Flow Statement (CFO / CFI / CFF)
 *
 * Accessible by: admin, super_admin, ceo, coo, finance
 */
class FinancialStatementsController extends BaseController
{
    /**
     * GET /api/v1/finance/balance-sheet
     *
     * Returns a snapshot balance sheet derived from Payment + Payout data.
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        $asOf = $request->input('as_of') ? now()->parse($request->input('as_of')) : now();

        // ── ASSETS ──────────────────────────────────────────────────────────
        // Cash & Cash Equivalents: total successful payments minus all completed payouts
        $totalRevenue  = Payment::where('status', 'successful')->where('created_at', '<=', $asOf)->sum('amount');
        $totalPayouts  = Payout::where('status', 'completed')->where('created_at', '<=', $asOf)->sum('amount');
        $cashBalance   = $totalRevenue - $totalPayouts;

        // Accounts Receivable: payments in pending/processing state
        $accountsReceivable = Payment::whereIn('status', ['pending', 'processing'])->where('created_at', '<=', $asOf)->sum('amount');

        // Prepaid & Other Current Assets (stubbed — can be extended with a prepaid table)
        $prepaidAssets = 0;

        $currentAssets    = $cashBalance + $accountsReceivable + $prepaidAssets;
        $nonCurrentAssets = 0; // Platform infrastructure (can be added manually via a settings override)

        $totalAssets = $currentAssets + $nonCurrentAssets;

        // ── LIABILITIES ──────────────────────────────────────────────────────
        // Accounts Payable: payouts pending or in-progress
        $accountsPayable = Payout::whereIn('status', ['pending', 'processing'])->where('created_at', '<=', $asOf)->sum('amount');

        // Deferred Revenue: refunded / partially settled payments
        $deferredRevenue = Payment::where('status', 'refunded')->where('created_at', '<=', $asOf)->sum('amount');

        $currentLiabilities    = $accountsPayable + $deferredRevenue;
        $nonCurrentLiabilities = 0;

        $totalLiabilities = $currentLiabilities + $nonCurrentLiabilities;

        // ── EQUITY ───────────────────────────────────────────────────────────
        // Retained Earnings = Total Assets - Total Liabilities (basic accounting identity)
        $retainedEarnings = $totalAssets - $totalLiabilities;
        $paidInCapital    = 0; // Equity investments — extend via a CapitalContribution model later
        $totalEquity      = $retainedEarnings + $paidInCapital;

        return $this->sendResponse([
            'as_of'    => $asOf->toDateString(),
            'currency' => 'NGN',
            'assets' => [
                'current' => [
                    'cash_and_equivalents'  => round($cashBalance, 2),
                    'accounts_receivable'   => round($accountsReceivable, 2),
                    'prepaid_assets'        => round($prepaidAssets, 2),
                    'total_current'         => round($currentAssets, 2),
                ],
                'non_current' => [
                    'platform_infrastructure' => round($nonCurrentAssets, 2),
                    'total_non_current'       => round($nonCurrentAssets, 2),
                ],
                'total' => round($totalAssets, 2),
            ],
            'liabilities' => [
                'current' => [
                    'accounts_payable' => round($accountsPayable, 2),
                    'deferred_revenue'  => round($deferredRevenue, 2),
                    'total_current'     => round($currentLiabilities, 2),
                ],
                'non_current' => [
                    'long_term_debt'   => round($nonCurrentLiabilities, 2),
                    'total_non_current'=> round($nonCurrentLiabilities, 2),
                ],
                'total' => round($totalLiabilities, 2),
            ],
            'equity' => [
                'paid_in_capital'  => round($paidInCapital, 2),
                'retained_earnings'=> round($retainedEarnings, 2),
                'total'            => round($totalEquity, 2),
            ],
            'check' => round($totalAssets - ($totalLiabilities + $totalEquity), 2), // should be 0
        ], 'Balance sheet retrieved.');
    }

    /**
     * GET /api/v1/finance/income-statement
     *
     * Returns a structured Income Statement for the given period.
     */
    public function incomeStatement(Request $request): JsonResponse
    {
        $period = $request->input('period', 'month'); // month | quarter | year
        [$startDate, $endDate] = $this->periodDates($period, $request);

        // Revenue
        $grossRevenue = Payment::where('status', 'successful')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $refunds = Payment::where('status', 'refunded')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $netRevenue = $grossRevenue - $refunds;

        // Cost of Revenue (therapist payouts = direct session delivery cost)
        $costOfRevenue = Payout::where('status', 'completed')
            ->where('type', 'session') // session payouts are COGS
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $grossProfit = $netRevenue - $costOfRevenue;
        $grossMargin = $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 1) : 0;

        // Operating Expenses = non-session payouts + employee salaries for the period
        $platformOpex = Payout::where('status', 'completed')
            ->where(function ($q) { $q->whereNull('type')->orWhere('type', '!=', 'session'); })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        // Salary cost: active salaries × months in period
        $months = max(1, (int) ceil($startDate->diffInDays($endDate) / 30));
        $salaryExpenses    = EmployeeSalary::active()->sum('base_salary') * $months;
        $operatingExpenses = $platformOpex + $salaryExpenses;

        $ebitda   = $grossProfit - $operatingExpenses;
        // Depreciation & Amortisation — stubbed (can be added via a fixed asset table)
        $da       = 0;
        $ebit     = $ebitda - $da;
        // Interest — stubbed
        $interest = 0;
        $ebt      = $ebit - $interest;
        // Tax provision — stubbed
        $tax      = 0;
        $netIncome = $ebt - $tax;

        // Monthly breakdown for the last 12 months (for chart use)
        $monthly = $this->monthlyBreakdown();

        return $this->sendResponse([
            'period'     => $period,
            'start_date' => $startDate->toDateString(),
            'end_date'   => $endDate->toDateString(),
            'currency'   => 'NGN',
            'revenue' => [
                'gross_revenue' => round($grossRevenue, 2),
                'refunds'       => round($refunds, 2),
                'net_revenue'   => round($netRevenue, 2),
            ],
            'cost_of_revenue' => round($costOfRevenue, 2),
            'gross_profit'    => round($grossProfit, 2),
            'gross_margin'    => $grossMargin,
            'operating_expenses' => round($operatingExpenses, 2),
            'ebitda'          => round($ebitda, 2),
            'depreciation_amortisation' => round($da, 2),
            'ebit'            => round($ebit, 2),
            'interest'        => round($interest, 2),
            'ebt'             => round($ebt, 2),
            'tax'             => round($tax, 2),
            'net_income'      => round($netIncome, 2),
            'monthly'         => $monthly,
        ], 'Income statement retrieved.');
    }

    /**
     * GET /api/v1/finance/cash-flow
     *
     * Returns the Cash Flow Statement (CFO / CFI / CFF).
     */
    public function cashFlow(Request $request): JsonResponse
    {
        $period = $request->input('period', 'month');
        [$startDate, $endDate] = $this->periodDates($period, $request);

        // ── OPERATING (CFO) ─────────────────────────────────────────────────
        $netIncomeCFO = Payment::where('status', 'successful')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount')
            - Payout::where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount');

        // Changes in working capital
        $changeInReceivables = - Payment::whereIn('status', ['pending', 'processing'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $changeInPayables = Payout::whereIn('status', ['pending', 'processing'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $cfo = $netIncomeCFO + $changeInReceivables + $changeInPayables;

        // ── INVESTING (CFI) ─────────────────────────────────────────────────
        // Platform/infrastructure capex — stubbed; extend with a CapexEntry model
        $capex       = 0;
        $investments = 0;
        $cfi         = - ($capex + $investments);

        // ── FINANCING (CFF) ─────────────────────────────────────────────────
        // Equity / debt raised / repaid — stubbed
        $equityRaised = 0;
        $debtRepaid   = 0;
        $cff          = $equityRaised - $debtRepaid;

        $netChange   = $cfo + $cfi + $cff;

        // Opening cash = all historical successful payments - completed payouts before period start
        $openingCash = Payment::where('status', 'successful')->where('created_at', '<', $startDate)->sum('amount')
            - Payout::where('status', 'completed')->where('created_at', '<', $startDate)->sum('amount');
        $closingCash = $openingCash + $netChange;

        // Monthly CFO trend
        $monthlyCFO = DB::table('payments')
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw('SUM(amount) as revenue'))
            ->where('status', 'successful')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($row) {
                $payouts = Payout::where('status', 'completed')
                    ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$row->month])
                    ->sum('amount');
                return [
                    'month'   => $row->month,
                    'revenue' => round((float) $row->revenue, 2),
                    'payouts' => round((float) $payouts, 2),
                    'net_cfo' => round((float) $row->revenue - $payouts, 2),
                ];
            });

        return $this->sendResponse([
            'period'     => $period,
            'start_date' => $startDate->toDateString(),
            'end_date'   => $endDate->toDateString(),
            'currency'   => 'NGN',
            'operating' => [
                'net_income'             => round($netIncomeCFO, 2),
                'change_in_receivables'  => round($changeInReceivables, 2),
                'change_in_payables'     => round($changeInPayables, 2),
                'total_cfo'              => round($cfo, 2),
            ],
            'investing' => [
                'capital_expenditure'    => round($capex, 2),
                'investments'            => round($investments, 2),
                'total_cfi'              => round($cfi, 2),
            ],
            'financing' => [
                'equity_raised'          => round($equityRaised, 2),
                'debt_repaid'            => round($debtRepaid, 2),
                'total_cff'              => round($cff, 2),
            ],
            'summary' => [
                'net_change'   => round($netChange, 2),
                'opening_cash' => round($openingCash, 2),
                'closing_cash' => round($closingCash, 2),
            ],
            'monthly_trend' => $monthlyCFO,
        ], 'Cash flow statement retrieved.');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function periodDates(string $period, Request $request): array
    {
        if ($request->has('start_date') && $request->has('end_date')) {
            return [now()->parse($request->input('start_date')), now()->parse($request->input('end_date'))];
        }
        return match($period) {
            'quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'year'    => [now()->startOfYear(), now()->endOfYear()],
            default   => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    private function monthlyBreakdown(): array
    {
        $months = collect(range(11, 0))->map(fn($i) => now()->subMonths($i)->format('Y-m'));

        return $months->map(function ($month) {
            $rev = Payment::where('status', 'successful')
                ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$month])
                ->sum('amount');
            $exp = Payout::where('status', 'completed')
                ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$month])
                ->sum('amount');
            return [
                'month'      => $month,
                'revenue'    => round((float) $rev, 2),
                'expenses'   => round((float) $exp, 2),
                'net_income' => round((float) ($rev - $exp), 2),
            ];
        })->values()->all();
    }
}
