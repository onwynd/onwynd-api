<?php

namespace App\Http\Controllers\API\V1\Tech;

use App\Http\Controllers\API\BaseController;
use App\Models\Deployment;
use App\Models\SystemLog;
use App\Models\User;

class DashboardController extends BaseController
{
    public function index()
    {
        // 1. System Status (Based on error logs in last hour)
        // Ensure SystemLog model exists and table has 'level' column
        $recentErrors = 0;
        try {
            $recentErrors = SystemLog::where('level', 'ERROR')
                ->where('created_at', '>=', now()->subHour())
                ->count();
        } catch (\Exception $e) {
            // Fallback if table/column missing
            $recentErrors = 0;
        }

        $status = 'Healthy';
        if ($recentErrors > 10) {
            $status = 'Critical';
        } elseif ($recentErrors > 0) {
            $status = 'Degraded';
        }

        // 2. Active Users
        $activeUsers = User::where('updated_at', '>=', now()->subMinutes(15))->count();

        // 3. Deployments
        $lastDeployment = null;
        $nextDeployment = null;

        try {
            $lastDeployment = Deployment::where('status', 'success')
                ->latest()
                ->first();

            $nextDeployment = Deployment::where('status', 'pending')
                ->orderBy('created_at', 'asc')
                ->first();
        } catch (\Exception $e) {
            // Fallback if table missing
        }

        // 4. Server Load (Mock)
        $serverLoad = [
            'cpu' => rand(10, 40).'%',
            'memory' => rand(30, 60).'%',
            'disk' => '30%',
        ];

        $data = [
            'system_status' => $status,
            'uptime' => '99.99%',
            'active_users' => $activeUsers,
            'server_load' => $serverLoad,
            'recent_errors' => $recentErrors,
            'deployments' => [
                'last' => $lastDeployment ? "{$lastDeployment->version} ({$lastDeployment->created_at->diffForHumans()})" : 'None',
                'next' => $nextDeployment ? "{$nextDeployment->version} (Pending)" : 'None',
            ],
        ];

        return $this->sendResponse($data, 'Tech Team dashboard data retrieved successfully.');
    }
}
