<?php

namespace App\Http\Controllers\API\V1\Cfo;

use App\Http\Controllers\API\BaseController;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CFO Finance Dashboard Controller
 * ──────────────────────────────────
 * Executive financial view for the CFO role.
 * All figures in NGN.
 *
 * GET /api/v1/finance/overview (shared with finance role, scoped by middleware)
 */
class DashboardController extends BaseController
{
    private const CACHE_TTL = 900; // 15 minutes — financial data refreshes faster

    public function overview()
    {
        $data = Cache::remember('cfo_finance_overview', self::CACHE_TTL, function () {
            return $this->buildOverview();
        });

        return $this->sendResponse($data, 'Finance overview retrieved');
    }

    private function buildOverview(): array
    {
        try {
            $totalRevenue = (float) Payment::where('status', 'successful')->sum('amount');
            $mrr          = (float) Payment::where('status', 'successful')
                                ->where('created_at', '>=', now()->startOfMonth())
                                ->sum('amount');
            $totalExpenses = (float) Payout::where('status', 'completed')->sum('amount');
            $expensesMtd   = (float) Payout::where('status', 'completed')
                                ->where('created_at', '>=', now()->startOfMonth())
                                ->sum('amount');
            $pendingPayouts = (float) Payout::where('status', 'pending')->sum('amount');

            $grossMargin = $totalRevenue > 0
                ? round((($totalRevenue - $totalExpenses) / $totalRevenue) * 100, 1)
                : 0;

            // Burn rate = average monthly expense over last 3 months
            $burnRate = (float) Payout::where('status', 'completed')
                ->where('created_at', '>=', now()->subMonths(3))
                ->sum('amount') / 3;

            $revenueSeries = $this->revenueSeries();

            return [
                'total_revenue'        => $totalRevenue,
                'mrr'                  => $mrr,
                'gross_margin'         => $grossMargin,
                'burn_rate'            => round($burnRate, 0),
                'cash_runway_months'   => null, // requires cash balance data
                'outstanding_invoices' => $pendingPayouts,
                'revenue_series'       => $revenueSeries,
                'recent_transactions'  => $this->recentTransactions(),
                'invoice_aging'        => $this->invoiceAging($pendingPayouts),
                'payroll_summary'      => [
                    'total'     => $expensesMtd,
                    'headcount' => User::whereNotIn('role', ['patient', 'therapist'])->count(),
                    'due_date'  => now()->endOfMonth()->toDateString(),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('CfoDashboardController failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function revenueSeries(): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end   = now()->subMonths($i)->endOfMonth();

            $revenue  = (float) Payment::where('status', 'successful')->whereBetween('created_at', [$start, $end])->sum('amount');
            $expenses = (float) Payout::where('status', 'completed')->whereBetween('created_at', [$start, $end])->sum('amount');

            $months[] = [
                'name'     => $start->format('M Y'),
                'revenue'  => $revenue,
                'expenses' => $expenses,
            ];
        }
        return $months;
    }

    private function recentTransactions(): array
    {
        $payments = Payment::where('status', 'successful')
            ->latest()
            ->limit(10)
            ->get(['id', 'amount', 'created_at', 'reference', 'type'])
            ->map(fn ($p) => [
                'id'          => $p->id,
                'description' => $p->reference ?? 'Payment #' . $p->id,
                'amount'      => (float) $p->amount,
                'type'        => 'credit',
                'date'        => $p->created_at->toIsoString(),
                'category'    => $p->type ?? 'Subscription',
            ])
            ->toArray();

        $payouts = Payout::where('status', 'completed')
            ->latest()
            ->limit(5)
            ->get(['id', 'amount', 'created_at', 'description'])
            ->map(fn ($p) => [
                'id'          => 'payout-' . $p->id,
                'description' => $p->description ?? 'Payout #' . $p->id,
                'amount'      => (float) $p->amount,
                'type'        => 'debit',
                'date'        => $p->created_at->toIsoString(),
                'category'    => 'Payout',
            ])
            ->toArray();

        $merged = array_merge($payments, $payouts);
        usort($merged, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return array_slice($merged, 0, 10);
    }

    private function invoiceAging(float $outstanding): array
    {
        // Simplified aging buckets using payout timing
        return [
            ['label' => '0–30 days',  'count' => Payout::where('status', 'pending')->where('created_at', '>=', now()->subDays(30))->count(),  'value' => (float) Payout::where('status', 'pending')->where('created_at', '>=', now()->subDays(30))->sum('amount')],
            ['label' => '31–60 days', 'count' => Payout::where('status', 'pending')->whereBetween('created_at', [now()->subDays(60), now()->subDays(31)])->count(), 'value' => (float) Payout::where('status', 'pending')->whereBetween('created_at', [now()->subDays(60), now()->subDays(31)])->sum('amount')],
            ['label' => '61–90 days', 'count' => Payout::where('status', 'pending')->whereBetween('created_at', [now()->subDays(90), now()->subDays(61)])->count(), 'value' => (float) Payout::where('status', 'pending')->whereBetween('created_at', [now()->subDays(90), now()->subDays(61)])->sum('amount')],
            ['label' => '90+ days',   'count' => Payout::where('status', 'pending')->where('created_at', '<', now()->subDays(90))->count(),   'value' => (float) Payout::where('status', 'pending')->where('created_at', '<', now()->subDays(90))->sum('amount')],
        ];
    }
}
