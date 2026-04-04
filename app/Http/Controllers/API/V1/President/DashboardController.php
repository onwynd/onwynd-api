<?php

namespace App\Http\Controllers\API\V1\President;

use App\Http\Controllers\API\BaseController;
use App\Models\Payment;
use App\Models\User;
use App\Models\Session;
use App\Models\Therapist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * President Dashboard Controller
 * ────────────────────────────────
 * Aggregated command-center view for the President role.
 * Read-only across all departments.
 *
 * GET /api/v1/president/overview
 */
class DashboardController extends BaseController
{
    private const CACHE_TTL = 1800; // 30 minutes

    public function overview()
    {
        $data = Cache::remember('president_overview', self::CACHE_TTL, function () {
            return $this->buildOverview();
        });

        return $this->sendResponse($data, 'President overview retrieved');
    }

    private function buildOverview(): array
    {
        try {
            $totalRevenue = (float) Payment::where('status', 'successful')->sum('amount');
            $activeUsers  = User::where('last_login_at', '>=', now()->subDays(30))->count();
            $sessionsMtd  = Session::where('created_at', '>=', now()->startOfMonth())->count();
            $employeeCount = User::whereNotIn('role', ['patient'])->count();

            $departments = $this->departmentHealth();
            $alerts      = $this->priorityAlerts();

            return [
                'total_revenue'        => $totalRevenue,
                'active_users'         => $activeUsers,
                'sessions_this_month'  => $sessionsMtd,
                'company_okr_health'   => null, // resolved by OKR service
                'open_alerts'          => count($alerts),
                'employee_count'       => $employeeCount,
                'department_health'    => $departments,
                'recent_alerts'        => $alerts,
            ];
        } catch (\Throwable $e) {
            Log::error('PresidentDashboardController failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function departmentHealth(): array
    {
        $departments = [
            ['department' => 'Finance',    'metric' => 'Revenue', 'value' => round((float) Payment::where('status', 'successful')->where('created_at', '>=', now()->startOfMonth())->sum('amount') / 1000, 0) . 'K NGN', 'status' => 'healthy'],
            ['department' => 'Sales',      'metric' => 'New Leads MTD', 'value' => DB::table('leads')->where('created_at', '>=', now()->startOfMonth())->count(), 'status' => 'healthy'],
            ['department' => 'Support',    'metric' => 'Open Tickets', 'value' => DB::table('support_tickets')->whereIn('status', ['open', 'pending'])->count(), 'status' => 'healthy'],
            ['department' => 'HR',         'metric' => 'Headcount', 'value' => User::whereNotIn('role', ['patient', 'therapist'])->count(), 'status' => 'healthy'],
            ['department' => 'Sessions',   'metric' => 'Sessions MTD', 'value' => Session::where('created_at', '>=', now()->startOfMonth())->count(), 'status' => 'healthy'],
            ['department' => 'Therapists', 'metric' => 'Active', 'value' => Therapist::where('status', 'approved')->count(), 'status' => 'healthy'],
        ];

        // Apply status heuristics
        foreach ($departments as &$dept) {
            if ($dept['department'] === 'Support') {
                $dept['status'] = $dept['value'] > 30 ? 'critical' : ($dept['value'] > 10 ? 'warning' : 'healthy');
            }
        }

        return $departments;
    }

    private function priorityAlerts(): array
    {
        // Placeholder — in production this would query an alerts/flags table
        return [];
    }
}
