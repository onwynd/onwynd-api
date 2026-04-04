<?php

namespace App\Http\Controllers\API\V1\Manager;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    /**
     * Get Manager / Operations Dashboard Data
     */
    public function index()
    {
        return response()->json([
            'team_overview' => [
                'total_members' => 25,
                'therapists' => 15,
                'support_staff' => 5,
                'admins' => 5,
            ],
            'performance_metrics' => [
                'average_response_time' => '5 mins',
                'customer_satisfaction' => 4.8,
                'tickets_resolved_today' => 45,
            ],
            'open_tasks' => [
                ['id' => 1, 'task' => 'Review Therapist Applications', 'priority' => 'High', 'due' => 'Today'],
                ['id' => 2, 'task' => 'Weekly Team Meeting', 'priority' => 'Medium', 'due' => 'Tomorrow'],
            ],
            'announcements' => [
                ['id' => 1, 'title' => 'New Policy Update', 'date' => '2023-10-25'],
                ['id' => 2, 'title' => 'Holiday Schedule', 'date' => '2023-10-20'],
            ],
        ]);
    }
}
