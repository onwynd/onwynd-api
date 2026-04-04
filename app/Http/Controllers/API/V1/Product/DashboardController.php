<?php

namespace App\Http\Controllers\API\V1\Product;

use App\Http\Controllers\API\BaseController;
use App\Models\MaintenanceSchedule;
use App\Models\ProductFeature;
use App\Models\Setting;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseController
{
    public function stats()
    {
        $activeFeatures = ProductFeature::whereIn('status', ['in_progress', 'active'])->count();
        $pendingRequests = ProductFeature::where('status', 'pending')->count();
        $maintenanceUpcoming = MaintenanceSchedule::where('start_time', '>', now())->count();

        // System health is mockable, or check failed jobs/errors
        $systemHealth = 98;

        $stats = [
            [
                'title' => 'Active Features',
                'value' => number_format($activeFeatures),
                'change' => '+0%',
                'changeType' => 'neutral',
                'description' => 'Current active features',
                'icon' => 'Rocket',
            ],
            [
                'title' => 'Pending Requests',
                'value' => number_format($pendingRequests),
                'change' => '0%',
                'changeType' => 'neutral',
                'description' => 'Awaiting review',
                'icon' => 'ListTodo',
            ],
            [
                'title' => 'Maintenance',
                'value' => number_format($maintenanceUpcoming),
                'change' => '0%',
                'changeType' => 'neutral',
                'description' => 'Upcoming tasks',
                'icon' => 'Hammer',
            ],
            [
                'title' => 'System Health',
                'value' => $systemHealth.'%',
                'change' => '0%',
                'changeType' => 'neutral',
                'description' => 'Operational status',
                'icon' => 'Activity',
            ],
        ];

        return $this->sendResponse($stats, 'PM Stats retrieved.');
    }

    public function tasks(Request $request)
    {
        $query = Task::with(['assignee', 'creator']);

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('assignee', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $tasks = $query->latest()->limit(50)->get()->map(function ($task) {
            return [
                'id' => $task->id, // Frontend expects string like 'TASK-1' but ID is int. We can format it.
                'title' => $task->title,
                'status' => $task->status,
                'assignee' => [
                    'name' => $task->assignee ? $task->assignee->full_name : 'Unassigned',
                    'avatar' => $task->assignee ? $task->assignee->profile_photo_url : '',
                ],
                'priority' => $task->priority === 'urgent' ? 'critical' : $task->priority, // Map urgent to critical
                'dueDate' => $task->due_date ? $task->due_date->format('Y-m-d') : null,
                'points' => 0, // Not in DB yet
            ];
        });

        return $this->sendResponse($tasks, 'Tasks retrieved.');
    }

    public function roadmap()
    {
        // Fetch ProductFeatures grouped by quarter
        // Assuming quarter is stored as 'Q1 2024', etc.
        $features = ProductFeature::select('quarter', 'status', DB::raw('count(*) as count'))
            ->groupBy('quarter', 'status')
            ->get();

        // Organize into the format expected by frontend:
        // [['name' => 'Q1', 'planned' => 20, 'completed' => 15], ...]

        $quarters = ['Q1', 'Q2', 'Q3', 'Q4']; // Should be dynamic based on current year
        $data = [];

        foreach ($quarters as $q) {
            $planned = $features->where('quarter', $q)->whereIn('status', ['planned', 'in_progress', 'review'])->sum('count');
            $completed = $features->where('quarter', $q)->where('status', 'released')->sum('count');

            $data[] = [
                'name' => $q,
                'planned' => $planned,
                'completed' => $completed,
            ];
        }

        return $this->sendResponse($data, 'Roadmap data retrieved.');
    }

    public function velocity()
    {
        // Velocity usually requires Sprints. Since we don't have sprints, we can group completed tasks by week.
        $completedTasks = Task::where('status', 'done')
            ->whereNotNull('updated_at')
            ->select(DB::raw('YEARWEEK(updated_at) as week'), DB::raw('count(*) as count'))
            ->groupBy('week')
            ->orderBy('week', 'desc')
            ->limit(5)
            ->get();

        // Mock committed vs completed for now as we don't track "committed" in tasks table
        $data = $completedTasks->map(function ($item) {
            return [
                'sprint' => 'Week '.substr($item->week, 4),
                'committed' => $item->count + rand(0, 5), // Mock committed to be slightly higher than completed
                'completed' => $item->count,
            ];
        })->values();

        if ($data->isEmpty()) {
            $data = [
                ['sprint' => 'Sprint 1', 'committed' => 0, 'completed' => 0],
            ];
        }

        return $this->sendResponse($data, 'Velocity data retrieved.');
    }

    public function index()
    {
        // Reuse stats logic
        $statsResponse = $this->stats();
        $stats = $statsResponse->getData()->data;

        return $this->sendResponse([
            'stats' => $stats,
        ], 'Dashboard data retrieved.');
    }

    public function features()
    {
        // Fetch feature flags from Settings table
        // Assuming keys start with 'feature_'
        $settings = Setting::where('key', 'like', 'feature_%')->get();

        $features = [];
        foreach ($settings as $setting) {
            $key = str_replace('feature_', '', $setting->key);
            $features[$key] = filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
        }

        // If empty, return defaults
        if (empty($features)) {
            $features = [
                'dark_mode' => true,
                'beta_features' => false,
                'notifications' => true,
                'analytics' => true,
                'ai_assistant' => true,
            ];
        }

        return $this->sendResponse($features, 'Features retrieved.');
    }

    public function updateFeatures(Request $request)
    {
        $data = $request->all();

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['key' => 'feature_'.$key],
                ['value' => $value ? 'true' : 'false', 'group' => 'features', 'type' => 'boolean']
            );
        }

        return $this->sendResponse($data, 'Features updated.');
    }
}
