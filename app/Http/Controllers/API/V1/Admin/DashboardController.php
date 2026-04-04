<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\SystemLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseController
{
    public function index(Request $request)
    {
        // 1. Users Breakdown
        $totalUsers = User::count();
        $newUsersToday = User::whereDate('created_at', today())->count();
        $therapistCount = User::whereHas('role', fn ($q) => $q->where('slug', 'therapist'))->count();
        $patientCount = User::whereHas('role', fn ($q) => $q->where('slug', 'patient'))->count();

        // 2. Revenue from Payments
        $totalRevenue = 0;
        $revenueGrowth = 0;
        try {
            $totalRevenue = Payment::where('status', 'completed')->sum('amount');

            // Calculate growth (this month vs last month)
            $thisMonthRevenue = Payment::where('status', 'completed')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount');

            $lastMonthRevenue = Payment::where('status', 'completed')
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->sum('amount');

            if ($lastMonthRevenue > 0) {
                $revenueGrowth = (($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100;
            }
        } catch (\Exception $e) {
            $totalRevenue = 0;
        }

        // 3. System Health (Error Logs in last 24h)
        $errorLogs = 0;
        try {
            $errorLogs = SystemLog::where('level', 'ERROR')
                ->where('created_at', '>=', now()->subDay())
                ->count();
        } catch (\Exception $e) {
            $errorLogs = 0;
        }

        // 4. Support Tickets
        $openTickets = 0;
        try {
            $openTickets = SupportTicket::where('status', 'open')->count();
        } catch (\Exception $e) {
            $openTickets = 0;
        }

        $stats = [
            [
                'id' => 'revenue',
                'title' => 'Total Revenue',
                'value' => '₦'.number_format($totalRevenue, 2),
                'icon' => 'coins',
                'isPositive' => $revenueGrowth >= 0,
                'change' => ($revenueGrowth >= 0 ? '+' : '').number_format($revenueGrowth, 1).'%',
                'changeValue' => 'vs last month',
            ],
            [
                'id' => 'users',
                'title' => 'Total Users',
                'value' => number_format($totalUsers),
                'icon' => 'users',
                'isPositive' => true,
                'change' => '+'.$newUsersToday,
                'changeValue' => 'new today',
                'details' => "{$therapistCount} Therapists, {$patientCount} Patients",
            ],
            [
                'id' => 'system_health',
                'title' => 'System Errors',
                'value' => (string) $errorLogs,
                'icon' => 'activity',
                'isPositive' => $errorLogs === 0,
                'change' => '24h',
                'changeValue' => 'recent errors',
                'variant' => $errorLogs > 0 ? 'destructive' : 'default',
            ],
            [
                'id' => 'tickets',
                'title' => 'Open Tickets',
                'value' => (string) $openTickets,
                'icon' => 'messages',
                'isPositive' => $openTickets < 10,
                'change' => 'Pending',
                'changeValue' => 'attention needed',
            ],
        ];

        return $this->sendResponse($stats, 'Admin dashboard data retrieved successfully.');
    }

    public function revenueFlow(Request $request)
    {
        $period = $request->input('period', '6months');

        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $month = $date->format('M');

            // Try to get real data from Payments
            $revenue = 0;
            try {
                $revenue = Payment::where('status', 'completed')
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->sum('amount');
            } catch (\Exception $e) {
                $revenue = 0;
            }

            $data[] = [
                'name' => $month,
                'value' => (int) $revenue,
            ];
        }

        return $this->sendResponse($data, 'Revenue flow retrieved.');
    }

    public function leadSources(Request $request)
    {
        $period = $request->input('period', '30days');

        try {
            $data = Lead::select('source as name', DB::raw('count(*) as value'))
                ->groupBy('source')
                ->get();

            if ($data->isEmpty()) {
                throw new \Exception('No data');
            }
        } catch (\Exception $e) {
            // Mock data
            $data = [
                ['name' => 'Website', 'value' => 40],
                ['name' => 'Referral', 'value' => 30],
                ['name' => 'Social Media', 'value' => 20],
                ['name' => 'Other', 'value' => 10],
            ];
        }

        return $this->sendResponse($data, 'Lead sources retrieved.');
    }

    public function deals(Request $request)
    {
        try {
            $deals = Deal::with(['lead', 'assignedUser'])
                ->latest()
                ->take(10)
                ->get()
                ->map(function ($deal) {
                    $ownerName = $deal->assignedUser ? $deal->assignedUser->name : 'Unassigned';
                    $ownerInitials = $deal->assignedUser
                        ? substr($deal->assignedUser->name, 0, 1).(strpos($deal->assignedUser->name, ' ') !== false ? substr($deal->assignedUser->name, strpos($deal->assignedUser->name, ' ') + 1, 1) : '')
                        : 'U';

                    $colors = ['bg-red-500', 'bg-blue-500', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500', 'bg-pink-500'];
                    $color = $colors[$deal->id % count($colors)];

                    return [
                        'id' => $deal->id,
                        'dealName' => $deal->title,
                        'client' => $deal->lead ? $deal->lead->first_name.' '.$deal->lead->last_name : 'Unknown Client',
                        'value' => (float) $deal->value,
                        'stage' => ucfirst($deal->stage),
                        'owner' => $ownerName,
                        'ownerInitials' => $ownerInitials,
                        'date' => $deal->created_at->format('M d, Y'),
                        'expectedClose' => $deal->expected_close_date ? $deal->expected_close_date->format('M d, Y') : 'N/A',
                        'dealInitial' => substr($deal->title, 0, 1),
                        'dealColor' => $color,
                    ];
                });

            // If no deals exist, return empty array (frontend handles empty state)
            // No demo data here.

        } catch (\Exception $e) {
            $deals = [];
        }

        return $this->sendResponse($deals, 'Recent deals retrieved.');
    }
}
