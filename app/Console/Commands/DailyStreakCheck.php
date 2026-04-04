<?php

namespace App\Console\Commands;

use App\Mail\StreakBreakNotification;
use App\Models\JournalEntry;
use App\Models\MindfulnessActivity;
use App\Models\MoodLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DailyStreakCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'streak:check-daily {--warn}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for broken daily streaks and send retention emails';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $warn = (bool) $this->option('warn');
        $this->info($warn ? 'Starting streak saver warnings...' : 'Starting daily streak check...');

        $yesterday = Carbon::yesterday()->startOfDay();
        $today = Carbon::today()->startOfDay();

        if ($warn) {
            return $this->sendStreakSaverWarnings();
        }

        $this->info('Checking for broken streaks...');
        $yesterday = Carbon::yesterday()->startOfDay();
        $today = Carbon::today()->startOfDay();

        $activeUsers = User::whereHas('mindfulnessActivities', function ($query) use ($yesterday) {
            $query->where('completed_at', '>=', $yesterday->copy()->subDays(6))
                ->where('completed_at', '<', $yesterday);
        })
            ->orWhereHas('journalEntries', function ($query) use ($yesterday) {
                $query->where('created_at', '>=', $yesterday->copy()->subDays(6))
                    ->where('created_at', '<', $yesterday);
            })
            ->orWhereHas('moodCheckIns', function ($query) use ($yesterday) {
                $query->where('created_at', '>=', $yesterday->copy()->subDays(6))
                    ->where('created_at', '<', $yesterday);
            })
            ->where('email_verified_at', '!=', null)
            ->where('is_active', true)
            ->chunk(100, function ($users) use ($yesterday, $today) {
                foreach ($users as $user) {
                    $this->checkUserStreak($user, $yesterday, $today);
                }
            });

        $this->info('Daily streak check completed.');
    }

    private function sendStreakSaverWarnings()
    {
        $this->info('Sending streak saver warnings...');
        $tz = 'Africa/Lagos';
        $today = Carbon::now($tz)->startOfDay();
        $endOfDay = Carbon::now($tz)->endOfDay();

        $users = User::whereHas('streak', function ($q) use ($today) {
            $q->where('current_streak', '>=', 5)
                ->whereDate('last_activity_date', '<', $today);
        })->where('email_verified_at', '!=', null)
            ->where('is_active', true)
            ->chunk(100, function ($chunk) use ($endOfDay) {
                foreach ($chunk as $user) {
                    try {
                        $streak = $user->streak;
                        if (! $streak) {
                            continue;
                        }
                        $hoursLeft = Carbon::now('Africa/Lagos')->diffInHours($endOfDay, false);
                        if ($hoursLeft <= 0) {
                            continue;
                        }
                        \Illuminate\Support\Facades\Mail::to($user->email)
                            ->queue(new \App\Mail\StreakSaverEmail($user, (int) $streak->current_streak, (int) $hoursLeft));
                        $this->info("Streak saver sent to {$user->email} ({$streak->current_streak} days, {$hoursLeft}h left)");
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('Streak saver warn failed', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
        $this->info('Streak saver warnings completed.');
    }

    private function checkUserStreak($user, $yesterday, $today)
    {
        // Check if user had any wellness activity yesterday
        $hadActivityYesterday = $this->userHadActivity($user, $yesterday, $today);

        if (! $hadActivityYesterday) {
            // Calculate their current streak (days since last activity)
            $lastActivityDate = $this->getLastActivityDate($user);
            $daysSinceActivity = $lastActivityDate ? $lastActivityDate->diffInDays($yesterday) : null;

            // Only notify if they had a meaningful streak (3+ days) or if it's been 2+ days
            if ($daysSinceActivity && $daysSinceActivity >= 2) {
                $this->sendStreakBreakNotification($user, $daysSinceActivity);
            }
        }
    }

    private function userHadActivity($user, $startDate, $endDate)
    {
        $mindfulness = MindfulnessActivity::where('user_id', $user->id)
            ->where('completed_at', '>=', $startDate)
            ->where('completed_at', '<', $endDate)
            ->exists();

        if ($mindfulness) {
            return true;
        }

        $journal = JournalEntry::where('user_id', $user->id)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<', $endDate)
            ->exists();

        if ($journal) {
            return true;
        }

        $mood = MoodLog::where('user_id', $user->id)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<', $endDate)
            ->exists();

        return $mood;
    }

    private function getLastActivityDate($user)
    {
        $lastMindfulness = MindfulnessActivity::where('user_id', $user->id)
            ->orderBy('completed_at', 'desc')
            ->first();

        $lastJournal = JournalEntry::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $lastMood = MoodLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $dates = collect();

        if ($lastMindfulness) {
            $dates->push($lastMindfulness->completed_at);
        }

        if ($lastJournal) {
            $dates->push($lastJournal->created_at);
        }

        if ($lastMood) {
            $dates->push($lastMood->created_at);
        }

        return $dates->max();
    }

    private function sendStreakBreakNotification($user, $daysSinceActivity)
    {
        try {
            // Log the streak break for analytics
            Log::info('User streak broken', [
                'user_id' => $user->id,
                'email' => $user->email,
                'days_since_activity' => $daysSinceActivity,
                'notification_type' => 'streak_break_retention',
                'event_type' => 'streak_broken',
            ]);

            Mail::to($user->email)->queue(new StreakBreakNotification($user, $daysSinceActivity));

            $this->info("Streak break notification queued for: {$user->email} ({$daysSinceActivity} days since activity)");

        } catch (\Exception $e) {
            Log::error('Failed to send streak break notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
