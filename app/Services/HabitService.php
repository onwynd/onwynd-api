<?php

namespace App\Services;

use App\Models\Habit;
use App\Models\HabitLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HabitService
{
    protected $gamificationService;

    public function __construct(GamificationService $gamificationService)
    {
        $this->gamificationService = $gamificationService;
    }

    public function logHabit(Habit $habit, $date, $status = 'completed', $count = 1, $notes = null)
    {
        return DB::transaction(function () use ($habit, $date, $status, $count, $notes) {
            $log = HabitLog::updateOrCreate(
                ['habit_id' => $habit->id, 'date' => $date],
                ['status' => $status, 'count' => $count, 'notes' => $notes]
            );

            if ($status === 'completed') {
                $this->updateStreak($habit);
                $this->gamificationService->checkMilestoneBadges($habit->user, $habit->logs()->where('status', 'completed')->count());
            }

            return $log;
        });
    }

    protected function updateStreak(Habit $habit)
    {
        // Simple daily streak logic
        // Get all completed logs ordered by date desc
        $logs = $habit->logs()
            ->where('status', 'completed')
            ->orderBy('date', 'desc')
            ->get();

        if ($logs->isEmpty()) {
            $habit->update(['streak' => 0]);

            return;
        }

        $streak = 0;
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $lastDate = Carbon::parse($logs->first()->date);

        // If last completion was today or yesterday, streak is alive
        if ($lastDate->lt($yesterday)) {
            $habit->update(['streak' => 0]);

            return;
        }

        // Calculate streak
        $currentDate = $lastDate;
        foreach ($logs as $log) {
            $logDate = Carbon::parse($log->date);
            if ($logDate->eq($currentDate)) {
                $streak++;
                $currentDate->subDay();
            } elseif ($logDate->lt($currentDate)) {
                break; // Gap found
            }
        }

        $longest = max($habit->longest_streak, $streak);
        $habit->update([
            'streak' => $streak,
            'longest_streak' => $longest,
        ]);

        $this->gamificationService->checkStreakBadges($habit->user, $streak);
    }
}
