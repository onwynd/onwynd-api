<?php

namespace App\Http\Controllers\API\V1\Marketing;

use App\Http\Controllers\API\BaseController;
use App\Models\Lead;
use App\Models\MarketingCampaign;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseController
{
    public function leadSources(Request $request)
    {
        $period = $request->query('period', 'Last 30 days');
        $days = 30;
        if ($period === 'Last 7 days') {
            $days = 7;
        } elseif ($period === 'Last 90 days') {
            $days = 90;
        } elseif ($period === 'Last Year') {
            $days = 365;
        }

        $startDate = Carbon::now()->subDays($days);

        $leads = Lead::where('created_at', '>=', $startDate)
            ->selectRaw('source, count(*) as count')
            ->groupBy('source')
            ->get();

        // Map to frontend format
        $data = $leads->map(function ($item) {
            $name = $item->source ?: 'Direct';
            $color = $this->getSourceColor($name);

            return [
                'name' => ucfirst($name),
                'value' => $item->count,
                'color' => $color,
            ];
        });

        return $this->sendResponse($data, 'Lead sources retrieved successfully.');
    }

    private function getSourceColor($source)
    {
        $source = strtolower($source);
        if (str_contains($source, 'google')) {
            return '#DB4437';
        }
        if (str_contains($source, 'facebook')) {
            return '#1877F2';
        }
        if (str_contains($source, 'linkedin')) {
            return '#0A66C2';
        }
        if (str_contains($source, 'twitter')) {
            return '#1DA1F2';
        }
        if (str_contains($source, 'instagram')) {
            return '#E1306C';
        }
        if (str_contains($source, 'direct')) {
            return '#10b981';
        }

        return '#71717a'; // default gray
    }

    public function index(Request $request)
    {
        // Calculate total spend from metrics
        $campaigns = MarketingCampaign::whereNotNull('metrics')->get();
        $totalSpent = 0;
        foreach ($campaigns as $campaign) {
            $metrics = $campaign->metrics;
            if (is_string($metrics)) {
                $metrics = json_decode($metrics, true);
            }
            if (isset($metrics['spend'])) {
                $totalSpent += $metrics['spend'];
            }
        }

        $activeCampaignsCount = MarketingCampaign::where('status', 'active')->count();
        $totalBudget = MarketingCampaign::sum('budget');

        // Leads count
        $totalLeads = Lead::count();
        $newLeads = Lead::where('created_at', '>=', Carbon::now()->subDays(7))->count();

        // Return array of Stat objects for frontend
        $stats = [
            [
                'title' => 'Active Campaigns',
                'value' => (string) $activeCampaignsCount,
                'change' => '+2', // Mock change
                'changeType' => 'increase',
                'iconName' => 'Activity',
                'description' => 'from last month',
            ],
            [
                'title' => 'Total Leads',
                'value' => number_format($totalLeads),
                'change' => "+$newLeads",
                'changeType' => 'increase',
                'iconName' => 'Users',
                'description' => 'new this week',
            ],
            [
                'title' => 'Total Spend',
                'value' => '$'.number_format($totalSpent, 2),
                'change' => '+12%', // Mock
                'changeType' => 'increase',
                'iconName' => 'TrendingUp',
                'description' => 'from last month',
            ],
            [
                'title' => 'Conversion Rate',
                'value' => $totalLeads > 0 ? '2.4%' : '0%', // Mock
                'change' => '+0.4%',
                'changeType' => 'increase',
                'iconName' => 'MousePointer',
                'description' => 'from last month',
            ],
        ];

        return $this->sendResponse($stats, 'Marketing dashboard data retrieved successfully.');
    }

    /**
     * Signup source breakdown — aggregate counts only, no user details.
     * Available to: marketing, admin, ceo, coo
     *
     * GET /api/v1/marketing/signup-sources?period=30
     */
    public function signupSources(Request $request)
    {
        $days = (int) $request->query('period', 30);
        $since = Carbon::now()->subDays($days);

        // Source breakdown (utm_source / 'direct')
        $sources = User::select('signup_source', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $since)
            ->groupBy('signup_source')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'name'  => ucfirst($row->signup_source ?? 'direct'),
                'value' => $row->count,
                'color' => $this->getSourceColor($row->signup_source ?? 'direct'),
            ]);

        // Auth provider breakdown (email vs google vs phone)
        $providers = User::select('auth_provider', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $since)
            ->whereNotNull('auth_provider')
            ->groupBy('auth_provider')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'name'  => ucfirst($row->auth_provider),
                'value' => $row->count,
            ]);

        // Daily signups trend
        $trend = User::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', $since)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => $row->date, 'signups' => $row->count]);

        return $this->sendResponse([
            'sources'   => $sources,
            'providers' => $providers,
            'trend'     => $trend,
            'period'    => $days,
        ], 'Signup sources retrieved.');
    }
}
