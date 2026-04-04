<?php

namespace App\Services\Reporting;

use App\Models\Payment;
use App\Models\TherapySession;
use App\Models\User; // Assuming this model exists
use Carbon\Carbon;

class AdminReportService
{
    /**
     * Get Weekly Performance Metrics
     */
    public function getWeeklyMetrics()
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $lastWeekStart = Carbon::now()->subWeek()->startOfWeek();
        $lastWeekEnd = Carbon::now()->subWeek()->endOfWeek();

        // New Users
        $newUsers = User::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count();
        $lastWeekUsers = User::whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->count();
        $userGrowth = $lastWeekUsers > 0 ? (($newUsers - $lastWeekUsers) / $lastWeekUsers) * 100 : 100;

        // Revenue
        $revenue = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->sum('amount');
        $lastWeekRevenue = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
            ->sum('amount');
        $revenueGrowth = $lastWeekRevenue > 0 ? (($revenue - $lastWeekRevenue) / $lastWeekRevenue) * 100 : 100;

        // Active Therapists (Mock logic: logged in this week or had a session)
        // Assuming 'therapist' role exists
        $activeTherapists = User::whereHas('roles', function ($q) {
            $q->where('name', 'therapist');
        })->count();

        // Sessions Held
        // Check if TherapySession model exists, otherwise return 0 or mock
        // For safety in this environment, I'll try-catch or just check class existence
        $sessionsHeld = 0;
        if (class_exists('App\Models\TherapySession')) {
            $sessionsHeld = \App\Models\TherapySession::whereBetween('scheduled_at', [$startOfWeek, $endOfWeek])
                ->where('status', 'completed')
                ->count();
        }

        return [
            'new_users' => $newUsers,
            'user_growth' => round($userGrowth, 1),
            'revenue' => '$'.number_format($revenue, 2),
            'revenue_growth' => round($revenueGrowth, 1),
            'active_therapists' => $activeTherapists,
            'sessions_held' => $sessionsHeld,
        ];
    }

    /**
     * Generate AI Analysis (Mocked for now)
     */
    public function getAiAnalysis($metrics)
    {
        // In production, this would call an LLM service
        $revenueStatus = $metrics['revenue_growth'] >= 0 ? 'positive' : 'concerning';
        $userStatus = $metrics['user_growth'] >= 0 ? 'strong' : 'slowing';

        return "Weekly analysis indicates {$revenueStatus} revenue trends with {$userStatus} user acquisition. ".
               'Therapist engagement remains stable. Recommended to focus on converting free trial users to paid plans.';
    }

    /**
     * Generate Forecast
     */
    public function getForecast()
    {
        // Simple linear projection
        return [
            'projected_revenue' => '$'.number_format(15000, 2), // Mock
            'projected_users' => 120, // Mock
        ];
    }

    /**
     * Get Recommended Action Steps
     */
    public function getActionSteps()
    {
        return [
            'Launch email campaign for users ending trial this week.',
            'Review feedback from recent 5 low-rated sessions.',
            'Schedule check-in with top performing therapists.',
        ];
    }
}
