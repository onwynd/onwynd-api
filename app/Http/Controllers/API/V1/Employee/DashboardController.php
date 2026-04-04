<?php

namespace App\Http\Controllers\API\V1\Employee;

use App\Http\Controllers\API\BaseController;
use App\Models\Meeting;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends BaseController
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $tasks = Task::where('assigned_to', $user->id)
            ->where('status', '!=', 'completed')
            ->orderBy('due_date', 'asc')
            ->take(5)
            ->get();

        $meetings = Meeting::whereHas('attendees', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->where('start_time', '>', now())
            ->orderBy('start_time', 'asc')
            ->take(3)
            ->get();

        return $this->sendResponse([
            'tasks' => $tasks,
            'upcoming_meetings' => $meetings,
            'announcements' => [], // Placeholder
        ], 'Employee dashboard data retrieved.');
    }
}
