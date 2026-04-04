<?php

namespace App\Http\Controllers\API\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Payroll;
use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Monthly financial flow for Manager dashboard
     * Returns last 12 months moneyIn/moneyOut with MoM change
     */
    public function index(Request $request)
    {
        $period = $request->query('period', 'year');
        $end = Carbon::now()->startOfMonth();
        $start = (clone $end)->subMonths(11);

        // Build monthly buckets
        $months = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        // Money In: successful payments
        $payments = Payment::select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"),
            DB::raw('SUM(amount) as total')
        )
            ->where('status', 'successful')
            ->whereBetween('created_at', [$start->copy()->startOfMonth(), $end->copy()->endOfMonth()])
            ->groupBy('ym')
            ->pluck('total', 'ym');

        // Money Out: completed payouts + payroll + refunds (if available)
        $payouts = Payout::select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"),
            DB::raw('SUM(amount) as total')
        )
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start->copy()->startOfMonth(), $end->copy()->endOfMonth()])
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $payrolls = Payroll::select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"),
            DB::raw('SUM(amount) as total')
        )
            ->whereBetween('created_at', [$start->copy()->startOfMonth(), $end->copy()->endOfMonth()])
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $refunds = Refund::select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"),
            DB::raw('SUM(amount) as total')
        )
            ->whereBetween('created_at', [$start->copy()->startOfMonth(), $end->copy()->endOfMonth()])
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $result = [];
        $prevIn = null;
        $prevOut = null;
        foreach ($months as $ym) {
            $in = (float) ($payments[$ym] ?? 0);
            $out = (float) (($payouts[$ym] ?? 0) + ($payrolls[$ym] ?? 0) + ($refunds[$ym] ?? 0));

            $inChange = $prevIn !== null && $prevIn != 0
                ? (($in - $prevIn) / $prevIn) * 100
                : 0.0;
            $outChange = $prevOut !== null && $prevOut != 0
                ? (($out - $prevOut) / $prevOut) * 100
                : 0.0;

            $result[] = [
                'month' => Carbon::createFromFormat('Y-m', $ym)->format('M'),
                'moneyIn' => round($in, 2),
                'moneyOut' => round($out, 2),
                'moneyInChange' => round($inChange, 2),
                'moneyOutChange' => round($outChange, 2),
            ];

            $prevIn = $in;
            $prevOut = $out;
        }

        return response()->json($result);
    }
}
