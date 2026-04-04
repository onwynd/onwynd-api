<?php

namespace App\Http\Controllers\API\V1\Secretary;

use App\Http\Controllers\API\BaseController;
use App\Models\Document;
use App\Models\Task;
use App\Models\TherapySession;
use App\Models\User;
use App\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    public function index()
    {
        // For backwards compatibility or simplified view
        $todayAppointments = TherapySession::whereDate('scheduled_at', Carbon::today())->count();
        $pendingVisitors = Visitor::where('status', 'checked_in')->whereNull('check_out_time')->count();
        $newMessages = 5; // Placeholder or count unread messages

        $data = [
            'overview' => [
                'today_appointments' => $todayAppointments,
                'pending_confirmations' => $pendingVisitors, // Reusing field name for compatibility
                'new_messages' => $newMessages,
            ],
            // ... other mock or real data
        ];

        return $this->sendResponse($data, 'Secretary dashboard data retrieved successfully.');
    }

    public function stats()
    {
        $today = Carbon::today();

        $todayAppointments = TherapySession::whereDate('scheduled_at', $today)->count();
        $checkedInVisitors = Visitor::whereDate('check_in_time', $today)->count();
        $pendingTasks = Task::where('status', '!=', 'completed')->count();
        $activeDocs = Document::count();

        // Calculate trends (mock logic for now or real comparison with yesterday)
        $yesterdayAppointments = TherapySession::whereDate('scheduled_at', Carbon::yesterday())->count();
        $appointmentTrend = $yesterdayAppointments > 0
            ? (($todayAppointments - $yesterdayAppointments) / $yesterdayAppointments) * 100
            : 0;

        // Calculate task trend (vs yesterday)
        $yesterdayTasks = Task::whereDate('created_at', Carbon::yesterday())->count();
        $todayTasks = Task::whereDate('created_at', $today)->count();
        $taskTrend = $yesterdayTasks > 0
            ? (($todayTasks - $yesterdayTasks) / $yesterdayTasks) * 100
            : 0;

        $stats = [
            [
                'id' => 'appointments',
                'title' => 'Today\'s Appointments',
                'value' => (string) $todayAppointments,
                'change' => ($appointmentTrend > 0 ? '+' : '').round($appointmentTrend, 1).'%',
                'trend' => $appointmentTrend >= 0 ? 'up' : 'down',
                'icon' => 'calendar',
            ],
            [
                'id' => 'visitors',
                'title' => 'Checked-in Visitors',
                'value' => (string) $checkedInVisitors,
                'change' => 'Today',
                'trend' => 'neutral',
                'icon' => 'users',
            ],
            [
                'id' => 'tasks',
                'title' => 'Pending Tasks',
                'value' => (string) $pendingTasks,
                'change' => ($taskTrend > 0 ? '+' : '').round($taskTrend, 1).'%',
                'trend' => $taskTrend < 0 ? 'up' : 'down', // Fewer pending tasks is good? Or more new tasks? Let's say neutral.
                'icon' => 'activity',
            ],
            [
                'id' => 'documents',
                'title' => 'Active Documents',
                'value' => (string) $activeDocs,
                'change' => 'Total',
                'trend' => 'neutral',
                'icon' => 'file-text',
            ],
        ];

        return $this->sendResponse($stats, 'Stats retrieved successfully.');
    }

    public function chartData(Request $request)
    {
        $period = $request->query('period', 'week');

        // Real data: Therapy Sessions count per day for the last 7 days
        $endDate = Carbon::today();
        $startDate = Carbon::today()->subDays(6);

        $data = [];
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $appointments = TherapySession::whereDate('scheduled_at', $currentDate)->count();
            $visitors = Visitor::whereDate('created_at', $currentDate)->count();

            $data[] = [
                'name' => $currentDate->format('D'), // Mon, Tue...
                'appointments' => $appointments,
                'visitors' => $visitors,
            ];
            $currentDate->addDay();
        }

        return $this->sendResponse($data, 'Chart data retrieved.');
    }

    public function calendar(Request $request)
    {
        $query = TherapySession::with(['patient', 'therapist']);

        if ($request->has('start') && $request->has('end')) {
            $query->whereBetween('scheduled_at', [$request->start, $request->end]);
        } else {
            $query->whereDate('scheduled_at', '>=', Carbon::today()->startOfMonth()->subMonths(1));
        }

        $sessions = $query->get();

        $events = $sessions->map(function ($session) {
            return [
                'id' => (string) $session->id,
                'title' => 'Session: '.($session->patient ? $session->patient->name : 'Unknown'),
                'start' => $session->scheduled_at->toIso8601String(),
                'end' => $session->scheduled_at->addMinutes($session->duration ?? 60)->toIso8601String(),
                'allDay' => false,
                'description' => $session->notes,
                'location' => 'Consultation Room',
                'type' => 'appointment',
                'patient' => $session->patient ? $session->patient->name : null,
                'doctor' => $session->therapist ? $session->therapist->name : null,
                'status' => $session->status,
            ];
        });

        return $this->sendResponse($events, 'Calendar events retrieved.');
    }

    public function tasks(Request $request)
    {
        $tasks = Task::orderBy('due_date', 'asc')->take(10)->get();

        $data = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date ? $task->due_date->format('Y-m-d') : null,
                'creator_id' => $task->creator_id,
                'assignee_id' => $task->assignee_id,
            ];
        });

        return $this->sendResponse($data, 'Tasks retrieved.');
    }

    public function people(Request $request)
    {
        // Users (patients/doctors/staff)
        $query = User::with('role');

        if ($request->has('role')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $people = $query->paginate(20);

        $data = $people->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ? $user->role->name : 'User',
                'status' => $user->is_active ? 'active' : 'offline', // Mapping for frontend status icons
                'avatar' => $user->profile_photo,
            ];
        });

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $data,
            $people->total(),
            $people->perPage(),
            $people->currentPage(),
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
        );

        return $this->sendResponse($paginated, 'People retrieved.');
    }

    public function documents(Request $request)
    {
        $documents = Document::with('owner')->latest()->take(10)->get();

        $data = $documents->map(function ($doc) {
            return [
                'id' => $doc->uuid ?? (string) $doc->id,
                'name' => $doc->name,
                'type' => $doc->file_type,
                'size' => $this->formatBytes($doc->file_size),
                'date' => $doc->created_at->format('Y-m-d'),
                'author' => $doc->owner ? $doc->owner->name : 'Unknown',
                'authorAvatar' => $doc->owner ? $doc->owner->profile_photo : null,
                'uploadedAt' => $doc->created_at->diffForHumans(),
                'icon' => $this->getIconType($doc->file_type),
            ];
        });

        return $this->sendResponse($data, 'Documents retrieved.');
    }

    private function formatBytes($bytes, $precision = 2)
    {
        if (! $bytes) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }

    private function getIconType($mimeType)
    {
        if (str_contains($mimeType, 'image')) {
            return 'file';
        }
        if (str_contains($mimeType, 'pdf')) {
            return 'files';
        }
        if (str_contains($mimeType, 'sheet') || str_contains($mimeType, 'csv')) {
            return 'checklist';
        }

        return 'file';
    }
}
