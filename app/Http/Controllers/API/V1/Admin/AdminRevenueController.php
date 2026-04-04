<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Admin\AdminLog;
use App\Models\PhysicalCenter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Response;

class AdminRevenueController extends BaseController
{
    /**
     * Get revenue summary by center.
     */
    public function byCenter(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        $revenueByCenter = PhysicalCenter::query()
            ->select('id', 'name', 'city')
            ->withCount([
                'bookings as total_bookings' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('scheduled_at', [$startDate, $endDate]);
                },
                'bookings as completed_bookings' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('scheduled_at', [$startDate, $endDate])
                        ->where('status', 'completed');
                },
            ])
            ->withSum([
                'bookings as total_revenue' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('scheduled_at', [$startDate, $endDate])
                        ->join('center_services', 'center_service_bookings.service_id', '=', 'center_services.id')
                        ->select(DB::raw('SUM(center_services.price)'));
                },
            ], 'price')
            ->get()
            ->map(function ($center) {
                return [
                    'center_id' => $center->id,
                    'center_name' => $center->name,
                    'city' => $center->city,
                    'total_bookings' => $center->total_bookings,
                    'completed_bookings' => $center->completed_bookings,
                    'completion_rate' => $center->total_bookings > 0
                        ? round(($center->completed_bookings / $center->total_bookings) * 100, 1)
                        : 0,
                    'total_revenue' => $center->total_revenue ?? 0,
                    'average_revenue_per_booking' => $center->total_bookings > 0
                        ? round(($center->total_revenue ?? 0) / $center->total_bookings, 2)
                        : 0,
                ];
            })
            ->sortByDesc('total_revenue')
            ->values();

        // Calculate totals
        $totals = [
            'total_centers' => $revenueByCenter->count(),
            'total_bookings' => $revenueByCenter->sum('total_bookings'),
            'total_revenue' => $revenueByCenter->sum('total_revenue'),
            'average_completion_rate' => $revenueByCenter->avg('completion_rate'),
            'average_revenue_per_center' => $revenueByCenter->count() > 0
                ? round($revenueByCenter->sum('total_revenue') / $revenueByCenter->count(), 2)
                : 0,
        ];

        return $this->sendResponse([
            'centers' => $revenueByCenter,
            'totals' => $totals,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ], 'Revenue summary by center retrieved successfully.');
    }

    /**
     * Get monthly revenue trends.
     */
    public function monthlyTrends(Request $request)
    {
        $months = $request->input('months', 12);
        $startDate = Carbon::now()->subMonths($months - 1)->startOfMonth();

        $monthlyRevenue = DB::table('center_service_bookings')
            ->join('center_services', 'center_service_bookings.service_id', '=', 'center_services.id')
            ->join('physical_centers', 'center_service_bookings.center_id', '=', 'physical_centers.id')
            ->select(
                DB::raw('DATE_FORMAT(center_service_bookings.scheduled_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as total_bookings'),
                DB::raw('SUM(center_services.price) as total_revenue'),
                DB::raw('AVG(center_services.price) as average_booking_value'),
                DB::raw('COUNT(DISTINCT physical_centers.id) as active_centers')
            )
            ->where('center_service_bookings.status', 'completed')
            ->where('center_service_bookings.scheduled_at', '>=', $startDate)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->month,
                    'total_bookings' => $item->total_bookings,
                    'total_revenue' => (float) $item->total_revenue,
                    'average_booking_value' => (float) $item->average_booking_value,
                    'active_centers' => $item->active_centers,
                ];
            });

        return $this->sendResponse($monthlyRevenue, 'Monthly revenue trends retrieved successfully.');
    }

    /**
     * Get service-wise revenue breakdown.
     */
    public function byService(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        $serviceNameColumn = Schema::hasColumn('center_services', 'service_name')
            ? 'center_services.service_name'
            : 'center_services.name';

        $revenueByService = DB::table('center_services')
            ->join('center_service_bookings', 'center_services.id', '=', 'center_service_bookings.service_id')
            ->select(
                'center_services.service_type',
                DB::raw("{$serviceNameColumn} as service_name"),
                'center_services.price',
                DB::raw('COUNT(center_service_bookings.id) as total_bookings'),
                DB::raw('SUM(center_services.price) as total_revenue')
            )
            ->where('center_service_bookings.status', 'completed')
            ->whereBetween('center_service_bookings.scheduled_at', [$startDate, $endDate])
            ->groupBy('center_services.id', 'center_services.service_type', $serviceNameColumn, 'center_services.price')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(function ($item) {
                return [
                    'service_type' => $item->service_type,
                    'service_name' => $item->service_name,
                    'price' => (float) $item->price,
                    'total_bookings' => $item->total_bookings,
                    'total_revenue' => (float) $item->total_revenue,
                    'percentage_of_total' => 0, // Will be calculated below
                ];
            });

        // Calculate percentage of total revenue
        $totalRevenue = $revenueByService->sum('total_revenue');
        $revenueByService->transform(function ($item) use ($totalRevenue) {
            $item['percentage_of_total'] = $totalRevenue > 0
                ? round(($item['total_revenue'] / $totalRevenue) * 100, 1)
                : 0;

            return $item;
        });

        return $this->sendResponse([
            'services' => $revenueByService,
            'total_revenue' => $totalRevenue,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ], 'Revenue by service retrieved successfully.');
    }

    /**
     * Get revenue analytics.
     */
    public function analytics(Request $request)
    {
        $period = $request->input('period', '30days');
        $endDate = Carbon::now();
        $startDate = $period === '7days' ? Carbon::now()->subDays(7) : Carbon::now()->subDays(30);

        // Revenue growth calculation
        $currentPeriodRevenue = DB::table('center_service_bookings')
            ->join('center_services', 'center_service_bookings.service_id', '=', 'center_services.id')
            ->where('center_service_bookings.status', 'completed')
            ->whereBetween('center_service_bookings.scheduled_at', [$startDate, $endDate])
            ->sum('center_services.price');

        $previousPeriodStart = $period === '7days'
            ? Carbon::now()->subDays(14)
            : Carbon::now()->subDays(60);
        $previousPeriodEnd = $period === '7days'
            ? Carbon::now()->subDays(7)
            : Carbon::now()->subDays(30);

        $previousPeriodRevenue = DB::table('center_service_bookings')
            ->join('center_services', 'center_service_bookings.service_id', '=', 'center_services.id')
            ->where('center_service_bookings.status', 'completed')
            ->whereBetween('center_service_bookings.scheduled_at', [$previousPeriodStart, $previousPeriodEnd])
            ->sum('center_services.price');

        $growthRate = $previousPeriodRevenue > 0
            ? round((($currentPeriodRevenue - $previousPeriodRevenue) / $previousPeriodRevenue) * 100, 1)
            : 0;

        // Peak hours analysis
        $peakHours = DB::table('center_service_bookings')
            ->select(
                DB::raw('HOUR(scheduled_at) as hour'),
                DB::raw('COUNT(*) as booking_count'),
                DB::raw('SUM(center_services.price) as revenue')
            )
            ->join('center_services', 'center_service_bookings.service_id', '=', 'center_services.id')
            ->where('center_service_bookings.status', 'completed')
            ->whereBetween('center_service_bookings.scheduled_at', [$startDate, $endDate])
            ->groupBy(DB::raw('HOUR(scheduled_at)'))
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        // Top performing centers
        $topCenters = DB::table('center_service_bookings')
            ->join('center_services', 'center_service_bookings.service_id', '=', 'center_services.id')
            ->join('physical_centers', 'center_service_bookings.center_id', '=', 'physical_centers.id')
            ->select(
                'physical_centers.name as center_name',
                DB::raw('COUNT(*) as booking_count'),
                DB::raw('SUM(center_services.price) as total_revenue')
            )
            ->where('center_service_bookings.status', 'completed')
            ->whereBetween('center_service_bookings.scheduled_at', [$startDate, $endDate])
            ->groupBy('physical_centers.id', 'physical_centers.name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        return $this->sendResponse([
            'revenue_growth' => [
                'current_period' => (float) $currentPeriodRevenue,
                'previous_period' => (float) $previousPeriodRevenue,
                'growth_rate' => $growthRate,
            ],
            'peak_hours' => $peakHours,
            'top_centers' => $topCenters,
            'period' => $period,
        ], 'Revenue analytics retrieved successfully.');
    }

    /**
     * Get platform revenue broken down across all 7 revenue streams.
     *
     * GET /api/v1/admin/revenue/breakdown
     */
    public function getRevenueBreakdown(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->input('end_date', now()->toDateString());
        $start     = $startDate . ' 00:00:00';
        $end       = $endDate   . ' 23:59:59';

        // ── Stream 1 & 6: Therapist commission + Booking fees ──────────────────
        $sessions = DB::table('therapy_sessions')
            ->where('status', 'completed')
            ->whereBetween(DB::raw('COALESCE(ended_at, updated_at)'), [$start, $end])
            ->selectRaw('
                SUM(COALESCE(session_rate, 0))                                  as total_session_revenue,
                SUM(COALESCE(commission_amount, 0))                             as total_therapist_payouts,
                SUM(COALESCE(session_rate, 0) - COALESCE(commission_amount, 0)) as commission_revenue,
                SUM(COALESCE(booking_fee_amount, 0))                            as booking_fee_revenue,
                COUNT(*)                                                        as session_count
            ')
            ->first();

        // ── Stream 2: D2C Subscriptions (individual users) ──────────────────────
        $d2cSubscriptionRevenue = DB::table('payments')
            ->where('payment_status', 'paid')
            ->where('payment_type', 'subscription')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount') ?? 0;

        // ── Stream 3: B2B Corporate Subscriptions ───────────────────────────────
        $corporateRevenue = DB::table('payments')
            ->where('payment_status', 'paid')
            ->where('payment_type', 'corporate_subscription')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount') ?? 0;

        // ── Stream 4: University / Institutional ────────────────────────────────
        $institutionalRevenue = 0;
        if (Schema::hasTable('institutional_contracts')) {
            $institutionalRevenue = DB::table('institutional_contracts')
                ->whereIn('status', ['active', 'completed'])
                ->whereBetween('created_at', [$start, $end])
                ->sum('contract_value') ?? 0;
        }
        // Also count institutional payments
        $institutionalRevenue += DB::table('payments')
            ->where('payment_status', 'paid')
            ->where('payment_type', 'institutional')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount') ?? 0;

        // ── Stream 5: Physical Wellness Centres ─────────────────────────────────
        $physicalWellnessRevenue = 0;
        if (Schema::hasTable('center_service_bookings') && Schema::hasTable('center_services')) {
            $physicalWellnessRevenue = DB::table('center_service_bookings')
                ->join('center_services', 'center_service_bookings.service_id', '=', 'center_services.id')
                ->where('center_service_bookings.status', 'completed')
                ->whereBetween('center_service_bookings.scheduled_at', [$start, $end])
                ->sum('center_services.price') ?? 0;
        }

        // ── Stream 7: Ancillary (partnerships, training, etc.) ──────────────────
        $ancillaryRevenue = DB::table('payments')
            ->where('payment_status', 'paid')
            ->where('payment_type', 'ancillary')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount') ?? 0;

        // ── Monthly breakdown (last 12 months) for chart ────────────────────────
        $monthlyBreakdown = DB::table('therapy_sessions')
            ->where('status', 'completed')
            ->where(DB::raw('COALESCE(ended_at, updated_at)'), '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("
                DATE_FORMAT(COALESCE(ended_at, updated_at), '%Y-%m') as month,
                SUM(COALESCE(session_rate, 0) - COALESCE(commission_amount, 0)) as commission_revenue,
                SUM(COALESCE(booking_fee_amount, 0)) as booking_fee_revenue,
                COUNT(*) as session_count
            ")
            ->groupByRaw("DATE_FORMAT(COALESCE(ended_at, updated_at), '%Y-%m')")
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Merge subscription payments into monthly
        $subMonthly = DB::table('payments')
            ->where('payment_status', 'paid')
            ->whereIn('payment_type', ['subscription', 'corporate_subscription', 'institutional'])
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as subscription_revenue")
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->pluck('subscription_revenue', 'month');

        $months = collect();
        $cursor = now()->subMonths(11)->startOfMonth();
        while ($cursor->lessThanOrEqualTo(now())) {
            $key = $cursor->format('Y-m');
            $row = $monthlyBreakdown->get($key);
            $months->push([
                'month'               => $key,
                'commission_revenue'  => (float) ($row->commission_revenue ?? 0),
                'booking_fee_revenue' => (float) ($row->booking_fee_revenue ?? 0),
                'subscription_revenue'=> (float) ($subMonthly->get($key, 0)),
                'session_count'       => (int)   ($row->session_count ?? 0),
            ]);
            $cursor->addMonth();
        }

        $commissionRevenue  = (float) ($sessions->commission_revenue ?? 0);
        $bookingFeeRevenue  = (float) ($sessions->booking_fee_revenue ?? 0);
        $totalPlatformRevenue = $commissionRevenue
            + $bookingFeeRevenue
            + $d2cSubscriptionRevenue
            + $corporateRevenue
            + $institutionalRevenue
            + $physicalWellnessRevenue
            + $ancillaryRevenue;

        return $this->sendResponse([
            'period' => ['start' => $startDate, 'end' => $endDate],
            'streams' => [
                ['key' => 'd2c_subscriptions',    'label' => 'D2C Subscriptions',          'revenue' => (float) $d2cSubscriptionRevenue,    'description' => 'Individual premium & recovery plans'],
                ['key' => 'corporate',             'label' => 'B2B Corporate',              'revenue' => (float) $corporateRevenue,          'description' => 'Corporate seat subscriptions'],
                ['key' => 'commission',            'label' => 'Therapist Commission',       'revenue' => $commissionRevenue,                 'description' => 'Platform cut of session fees'],
                ['key' => 'institutional',         'label' => 'University / Institutional', 'revenue' => (float) $institutionalRevenue,      'description' => 'Contract-based institutional plans'],
                ['key' => 'physical_wellness',     'label' => 'Physical Wellness Centres',  'revenue' => (float) $physicalWellnessRevenue,   'description' => 'Pay-per-visit & memberships'],
                ['key' => 'booking_fees',          'label' => 'Booking Fees',               'revenue' => $bookingFeeRevenue,                 'description' => 'Per-session convenience fee'],
                ['key' => 'ancillary',             'label' => 'Ancillary',                  'revenue' => (float) $ancillaryRevenue,          'description' => 'Partnerships, training & other'],
            ],
            'total_platform_revenue'  => $totalPlatformRevenue,
            'total_session_revenue'   => (float) ($sessions->total_session_revenue ?? 0),
            'total_therapist_payouts' => (float) ($sessions->total_therapist_payouts ?? 0),
            'session_count'           => (int)   ($sessions->session_count ?? 0),
            'monthly_breakdown'       => $months->values(),
        ], 'Revenue breakdown retrieved.');
    }

    /**
     * Export a branded PDF/CSV revenue report.
     *
     * GET /api/v1/admin/revenue/full-export
     */
    public function fullExport(Request $request)
    {
        $request->validate([
            'format'     => 'nullable|in:csv,pdf',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
        ]);

        $format    = $request->input('format', 'csv');
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->input('end_date', now()->toDateString());

        // Reuse breakdown data
        $breakdown = $this->getRevenueBreakdown($request)->getData(true)['data'];
        $streams   = $breakdown['streams'];
        $total     = $breakdown['total_platform_revenue'];
        $period    = $breakdown['period'];

        AdminLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'export_revenue_full',
            'target_type' => null,
            'target_id'   => null,
            'details'     => ['format' => $format, 'period' => $period],
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        if ($format === 'csv') {
            $rows   = [];
            $rows[] = ['Revenue Stream', 'Description', 'Amount (NGN)', '% of Total'];
            foreach ($streams as $s) {
                $pct    = $total > 0 ? round($s['revenue'] / $total * 100, 1) : 0;
                $rows[] = [$s['label'], $s['description'], number_format($s['revenue'], 2), $pct . '%'];
            }
            $rows[] = [];
            $rows[] = ['TOTAL', '', number_format($total, 2), '100%'];
            $rows[] = ['Period', $period['start'] . ' – ' . $period['end'], '', ''];
            $rows[] = ['Generated', now()->toDateTimeString(), '', ''];

            $csv = collect($rows)->map(fn ($r) => implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', $v) . '"', $r)))->implode("\n");

            return Response::make($csv, 200, [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => 'attachment; filename="onwynd-revenue-' . $startDate . '-to-' . $endDate . '.csv"',
            ]);
        }

        // PDF: return structured data; frontend renders with browser print / jsPDF
        return $this->sendResponse([
            'streams'    => $streams,
            'total'      => $total,
            'period'     => $period,
            'generated'  => now()->toDateTimeString(),
            'session_count'           => $breakdown['session_count'],
            'total_therapist_payouts' => $breakdown['total_therapist_payouts'],
            'monthly_breakdown'       => $breakdown['monthly_breakdown'],
        ], 'Revenue report ready for export.');
    }

    /**
     * Export revenue report.
     */
    public function export(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $format = $request->input('format', 'csv');

        $revenueData = $this->byCenter($request)->getData()->data;

        // Log the export
        AdminLog::create([
            'user_id' => $request->user()->id,
            'action' => 'export_revenue_report',
            'target_type' => null,
            'target_id' => null,
            'details' => [
                'date_range' => ['start' => $startDate, 'end' => $endDate],
                'format' => $format,
                'centers_count' => count($revenueData->centers),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse([
            'data' => $revenueData,
            'format' => $format,
            'export_date' => Carbon::now()->format('Y-m-d H:i:s'),
        ], 'Revenue report data prepared for export.');
    }
}
