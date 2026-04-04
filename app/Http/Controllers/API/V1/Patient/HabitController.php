<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Services\HabitService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HabitController extends BaseController
{
    protected $habitService;

    public function __construct(HabitService $habitService)
    {
        $this->habitService = $habitService;
    }

    public function index(Request $request)
    {
        $habits = $request->user()->habits()->where('is_archived', false)->get();

        return $this->sendResponse($habits, 'Habits retrieved.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly,custom',
            'start_date' => 'required|date',
            'target_count' => 'integer|min:1',
            'reminder_times' => 'nullable|array',
        ]);

        $habit = $request->user()->habits()->create($validated);

        return $this->sendResponse($habit, 'Habit created.');
    }

    public function show(Habit $habit)
    {
        if ($habit->user_id !== auth()->id()) {
            return $this->sendError('Unauthorized', [], 403);
        }

        return $this->sendResponse($habit->load('logs'), 'Habit details.');
    }

    public function update(Request $request, Habit $habit)
    {
        if ($habit->user_id !== auth()->id()) {
            return $this->sendError('Unauthorized', [], 403);
        }
        $habit->update($request->all());

        return $this->sendResponse($habit, 'Habit updated.');
    }

    public function destroy(Habit $habit)
    {
        if ($habit->user_id !== auth()->id()) {
            return $this->sendError('Unauthorized', [], 403);
        }
        $habit->update(['is_archived' => true]);

        return $this->sendResponse(null, 'Habit archived.');
    }

    public function toggle(Habit $habit)
    {
        if ($habit->user_id !== auth()->id()) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $habit->update(['is_archived' => ! $habit->is_archived]);

        return $this->sendResponse($habit, $habit->is_archived ? 'Habit deactivated.' : 'Habit activated.');
    }

    public function logs(Request $request, Habit $habit)
    {
        if ($habit->user_id !== auth()->id()) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $query = $habit->logs()->orderBy('date', 'desc');

        if ($request->has('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        return $this->sendResponse($query->get(), 'Habit logs retrieved.');
    }

    public function deleteLog(Habit $habit, $logId)
    {
        if ($habit->user_id !== auth()->id()) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $log = HabitLog::where('habit_id', $habit->id)->find($logId);

        if (! $log) {
            return $this->sendError('Log entry not found.', [], 404);
        }

        $log->delete();

        return $this->sendResponse(null, 'Habit log deleted.');
    }

    public function stats(Request $request)
    {
        $user = $request->user();
        $habits = $user->habits()->where('is_archived', false)->get();

        $totalHabits = $habits->count();
        $totalLogsToday = HabitLog::whereIn('habit_id', $habits->pluck('id'))
            ->whereDate('date', today())
            ->where('status', 'completed')
            ->count();

        $completionRates = $habits->map(function ($habit) {
            $total = $habit->logs()->count();
            $completed = $habit->logs()->where('status', 'completed')->count();

            return [
                'habit_id' => $habit->id,
                'habit_name' => $habit->name,
                'total_logs' => $total,
                'completed' => $completed,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
                'current_streak' => $habit->streak ?? 0,
                'longest_streak' => $habit->longest_streak ?? 0,
            ];
        });

        return $this->sendResponse([
            'total_habits' => $totalHabits,
            'completed_today' => $totalLogsToday,
            'habits' => $completionRates,
        ], 'Habit statistics retrieved.');
    }

    public function calendar(Request $request, Habit $habit)
    {
        if ($habit->user_id !== auth()->id()) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $month = $request->input('month', now()->format('Y-m')); // YYYY-MM
        [$year, $mon] = explode('-', $month);

        $start = Carbon::createFromDate($year, $mon, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $logs = $habit->logs()
            ->whereBetween('date', [$start, $end])
            ->get()
            ->keyBy(fn ($log) => Carbon::parse($log->date)->format('Y-m-d'));

        // Build calendar map: date => status|false
        $calendar = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $calendar[$key] = $logs->has($key) ? $logs[$key]->status : null;
            $cursor->addDay();
        }

        return $this->sendResponse($calendar, 'Habit calendar retrieved.');
    }

    public function log(Request $request, Habit $habit)
    {
        if ($habit->user_id !== auth()->id()) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'status' => 'required|in:completed,skipped,failed',
            'count' => 'integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $log = $this->habitService->logHabit(
            $habit,
            $validated['date'],
            $validated['status'],
            $validated['count'] ?? 1,
            $validated['notes'] ?? null
        );

        return $this->sendResponse($log, 'Habit logged.');
    }
}
