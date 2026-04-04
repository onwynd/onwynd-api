<?php

namespace App\Http\Controllers\API\V1\ProductManager;

use App\Http\Controllers\API\BaseController;
use App\Models\MaintenanceSchedule;
use App\Models\ProductFeature;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    public function index()
    {
        return $this->stats();
    }

    public function stats()
    {
        $totalFeatures = ProductFeature::count();
        $completedFeatures = ProductFeature::where('status', 'completed')->orWhere('status', 'released')->count();
        $pendingMaintenance = MaintenanceSchedule::where('status', 'pending')->count();
        $inProgressFeatures = ProductFeature::where('status', 'in_progress')->count();

        $stats = [
            [
                'title' => 'Total Features',
                'value' => (string) $totalFeatures,
                'change' => '+0%',
                'changeType' => 'neutral',
                'description' => 'All tracked features',
                'icon' => 'Activity',
            ],
            [
                'title' => 'Completed',
                'value' => (string) $completedFeatures,
                'change' => '',
                'changeType' => 'increase',
                'description' => 'Features delivered',
                'icon' => 'CheckCircle',
            ],
            [
                'title' => 'In Progress',
                'value' => (string) $inProgressFeatures,
                'change' => '',
                'changeType' => 'neutral',
                'description' => 'Currently being built',
                'icon' => 'Clock',
            ],
            [
                'title' => 'Pending Maintenance',
                'value' => (string) $pendingMaintenance,
                'change' => '',
                'changeType' => 'decrease',
                'description' => 'Schedules needing approval',
                'icon' => 'Tool',
            ],
        ];

        return $this->sendResponse($stats, 'PM Stats retrieved.');
    }

    public function tasks(Request $request)
    {
        // Return recent features as tasks
        $tasks = ProductFeature::orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($feature) {
                return [
                    'id' => $feature->id,
                    'title' => $feature->title,
                    'status' => $feature->status,
                    'priority' => $feature->priority,
                    'dueDate' => $feature->target_date ? $feature->target_date->format('Y-m-d') : null,
                ];
            });

        return $this->sendResponse($tasks, 'Tasks retrieved.');
    }

    public function roadmap()
    {
        // Aggregate features by quarter for the chart
        $data = ProductFeature::selectRaw('quarter, count(*) as total, sum(case when status in ("completed", "released") then 1 else 0 end) as completed')
            ->whereNotNull('quarter')
            ->groupBy('quarter')
            ->orderBy('quarter')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->quarter,
                    'planned' => $item->total,
                    'completed' => (int) $item->completed,
                ];
            });

        return $this->sendResponse($data, 'Roadmap data retrieved.');
    }

    public function velocity()
    {
        // Mock velocity data as we don't have sprints yet
        $data = [
            ['sprint' => 'Sprint 1', 'committed' => 30, 'completed' => 25],
            ['sprint' => 'Sprint 2', 'committed' => 35, 'completed' => 30],
            ['sprint' => 'Sprint 3', 'committed' => 40, 'completed' => 38],
        ];

        return $this->sendResponse($data, 'Velocity data retrieved.');
    }
}
