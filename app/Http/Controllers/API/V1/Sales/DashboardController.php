<?php

namespace App\Http\Controllers\API\V1\Sales;

use App\Http\Controllers\API\BaseController;
use App\Models\Deal;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseController
{
    /**
     * Legacy full dashboard (kept for /sales/dashboard route)
     */
    public function index()
    {
        $topPerformers = Deal::select('assigned_to', DB::raw('sum(value) as revenue'), DB::raw('count(*) as deals'))
            ->where('stage', 'closed_won')
            ->whereNotNull('assigned_to')
            ->with('assignedUser:id,first_name,last_name')
            ->groupBy('assigned_to')
            ->orderByDesc('revenue')
            ->take(5)
            ->get()
            ->map(function ($deal) {
                return [
                    'name' => $deal->assignedUser ? ($deal->assignedUser->first_name.' '.$deal->assignedUser->last_name) : 'Unknown',
                    'revenue' => $deal->revenue,
                    'deals' => $deal->deals,
                ];
            });

        $stats = [
            'pipeline_summary' => [
                'total_leads' => Lead::count(),
                'qualified_leads' => Lead::where('status', 'qualified')->count(),
                'proposals_sent' => Deal::where('stage', 'proposal')->count(),
                'closed_won' => Deal::where('stage', 'closed_won')->count(),
            ],
            'conversion_metrics' => [
                'win_rate' => $this->calculateWinRate(),
                'average_deal_size' => $this->calculateAverageDealSize(),
            ],
            'top_performers' => $topPerformers,
            'recent_deals' => Deal::with('lead:id,company')
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get()
                ->map(function ($deal) {
                    return [
                        'client' => $deal->lead->company ?? 'Unknown',
                        'value' => $deal->value,
                        'status' => $deal->stage,
                    ];
                }),
        ];

        return $this->sendResponse($stats, 'Sales dashboard data retrieved successfully.');
    }

    /**
     * Returns StatCard[] for the sales store
     * Shape: { id, title, value, change, isPositive, icon }
     */
    public function stats(Request $request)
    {
        $ownerId = $request->owner_id;

        // Helper to apply owner scope
        $applyScope = function ($query) use ($ownerId) {
            if ($ownerId) {
                $query->where('owner_id', $ownerId);
            }
        };

        $leadQuery = Lead::query();
        $dealQuery = Deal::query();
        $applyScope($leadQuery);
        $applyScope($dealQuery);

        // Pipeline Overview Stats
        $totalLeads = (clone $leadQuery)->count();
        $newLeadsWeek = (clone $leadQuery)->where('created_at', '>=', Carbon::now()->startOfWeek())->count();
        $totalDeals = (clone $dealQuery)->count();
        $wonMonth = (clone $dealQuery)->where('stage', 'closed_won')->whereMonth('created_at', Carbon::now()->month)->count();
        $lostMonth = (clone $dealQuery)->where('stage', 'closed_lost')->whereMonth('created_at', Carbon::now()->month)->count();
        $pipelineValue = (clone $dealQuery)->whereIn('stage', ['prospecting', 'negotiation', 'proposal'])->sum('value');

        $wonTotal = (clone $dealQuery)->where('stage', 'closed_won')->count();
        $closedTotal = (clone $dealQuery)->whereIn('stage', ['closed_won', 'closed_lost'])->count();
        $conversionRate = $closedTotal > 0 ? round(($wonTotal / $closedTotal) * 100, 1) : 0;

        $stats = [
            [
                'id' => 'total_leads',
                'title' => 'Total Leads',
                'value' => number_format($totalLeads),
                'change' => '',
                'isPositive' => true,
                'icon' => 'users',
            ],
            [
                'id' => 'new_leads_week',
                'title' => 'New This Week',
                'value' => number_format($newLeadsWeek),
                'change' => '',
                'isPositive' => true,
                'icon' => 'user-plus',
            ],
            [
                'id' => 'total_deals',
                'title' => 'Total Deals',
                'value' => number_format($totalDeals),
                'change' => '',
                'isPositive' => true,
                'icon' => 'briefcase',
            ],
            [
                'id' => 'won_month',
                'title' => 'Won (Month)',
                'value' => number_format($wonMonth),
                'change' => '',
                'isPositive' => true,
                'icon' => 'check-circle',
            ],
            [
                'id' => 'lost_month',
                'title' => 'Lost (Month)',
                'value' => number_format($lostMonth),
                'change' => '',
                'isPositive' => false,
                'icon' => 'x-circle',
            ],
            [
                'id' => 'pipeline_value',
                'title' => 'Pipeline Value',
                'value' => '₦'.number_format($pipelineValue),
                'change' => '',
                'isPositive' => true,
                'icon' => 'dollar-sign',
            ],
            [
                'id' => 'conversion_rate',
                'title' => 'Conversion Rate',
                'value' => $conversionRate.'%',
                'change' => '',
                'isPositive' => true,
                'icon' => 'percent',
            ],
        ];

        return $this->sendResponse($stats, 'Sales stats retrieved.');
    }

    public function agentPerformance(Request $request)
    {
        // Only admin/CEO/COO should see this ideally, or sales manager
        // Aggregate stats by owner
        $agents = \App\Models\User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['sales', 'finder', 'relationship_manager']);
        })->with(['leads', 'deals'])->get();

        $performance = $agents->map(function ($agent) {
            $wonDeals = $agent->deals->where('stage', 'closed_won');
            $pipelineDeals = $agent->deals->whereIn('stage', ['prospecting', 'negotiation', 'proposal']);

            return [
                'id' => $agent->id,
                'agent' => $agent->first_name.' '.$agent->last_name,
                'role' => $agent->roles->pluck('name')->implode(', '),
                'leads' => $agent->leads->count(),
                'deals_won' => $wonDeals->count(),
                'pipeline_value' => $pipelineDeals->sum('value'),
                'avg_deal_size' => $wonDeals->avg('value') ?? 0,
                'last_activity' => $agent->last_seen_at, // Assuming last_seen_at exists on User
            ];
        });

        return $this->sendResponse($performance, 'Agent performance retrieved.');
    }

    /**
     * Returns RevenueFlow[] for charts
     * Shape: { name: string, thisYear: number, lastYear: number }
     */
    public function revenueFlow(Request $request)
    {
        $months = max(1, min(12, (int) $request->input('period', 6)));
        $data = [];
        $thisYear = Carbon::now()->year;
        $lastYear = $thisYear - 1;

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('M');
            $month = $date->month;

            $thisYearRevenue = Deal::where('stage', 'closed_won')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $month)
                ->sum('value');

            $lastYearRevenue = Deal::where('stage', 'closed_won')
                ->whereYear('created_at', $date->year - 1)
                ->whereMonth('created_at', $month)
                ->sum('value');

            $data[] = [
                'name' => $monthName,
                'thisYear' => (float) $thisYearRevenue,
                'lastYear' => (float) $lastYearRevenue,
            ];
        }

        return $this->sendResponse($data, 'Revenue flow retrieved.');
    }

    /**
     * Returns LeadSource[] for pie/donut chart
     * Shape: { name: string, value: number, color?: string }
     */
    public function leadSources(Request $request)
    {
        $sources = Lead::select('source', DB::raw('count(*) as total'))
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->groupBy('source')
            ->orderByDesc('total')
            ->get();

        $colors = ['#6e3ff3', '#df3674', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899'];

        $data = $sources->values()->map(function ($row, $i) use ($colors) {
            return [
                'name' => ucfirst($row->source),
                'value' => (int) $row->total,
                'color' => $colors[$i % count($colors)],
            ];
        })->toArray();

        // Ensure there is always at least some data
        if (empty($data)) {
            $data = [
                ['name' => 'Direct',   'value' => 0, 'color' => '#6e3ff3'],
                ['name' => 'Referral', 'value' => 0, 'color' => '#df3674'],
                ['name' => 'Website',  'value' => 0, 'color' => '#f59e0b'],
            ];
        }

        return $this->sendResponse($data, 'Lead sources retrieved.');
    }

    private function calculateWinRate(): string
    {
        $totalClosed = Deal::whereIn('stage', ['closed_won', 'closed_lost'])->count();
        if ($totalClosed === 0) {
            return '0%';
        }
        $won = Deal::where('stage', 'closed_won')->count();

        return round(($won / $totalClosed) * 100).'%';
    }

    private function calculateAverageDealSize(): string
    {
        $avg = Deal::where('stage', 'closed_won')->avg('value');

        return 'NGN '.number_format($avg ?? 0, 2);
    }
}
