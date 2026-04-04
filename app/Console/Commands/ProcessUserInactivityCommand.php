<?php

namespace App\Console\Commands;

use App\Jobs\UserInactivityJob;
use App\Models\MindfulnessActivity;
use App\Models\MoodTracking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessUserInactivityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:process-inactivity {--days=3 : Minimum days of inactivity} {--dry-run : Show what would be sent without sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process user inactivity and send retention emails';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysInactive = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("Processing user inactivity for users inactive for {$daysInactive}+ days...");

        $cutoffDate = Carbon::now()->subDays($daysInactive)->startOfDay();

        // Get users who haven't had any activity in the specified days
        $inactiveUsers = User::where('email_verified_at', '!=', null)
            ->where('is_active', true)
            ->where(function ($query) use ($cutoffDate) {
                // No mindfulness activities
                $query->whereDoesntHave('mindfulnessActivities', function ($q) use ($cutoffDate) {
                    $q->where('completed_at', '>=', $cutoffDate);
                })
                // No mood trackings
                    ->whereDoesntHave('moodTrackings', function ($q) use ($cutoffDate) {
                        $q->where('logged_at', '>=', $cutoffDate);
                    });
            })
            // But had activity in the last 30 days (so they're not completely new/dormant)
            ->where(function ($query) use ($cutoffDate) {
                $recentCutoff = Carbon::now()->subDays(30)->startOfDay();
                $query->whereHas('mindfulnessActivities', function ($q) use ($recentCutoff, $cutoffDate) {
                    $q->where('completed_at', '>=', $recentCutoff)
                        ->where('completed_at', '<', $cutoffDate);
                })
                    ->orWhereHas('moodTrackings', function ($q) use ($recentCutoff, $cutoffDate) {
                        $q->where('logged_at', '>=', $recentCutoff)
                            ->where('logged_at', '<', $cutoffDate);
                    });
            })
            ->chunk(100, function ($users) use ($dryRun, $daysInactive) {
                foreach ($users as $user) {
                    $this->processUser($user, $daysInactive, $dryRun);
                }
            });

        $this->info('User inactivity processing completed.');
    }

    private function processUser($user, $daysInactive, $dryRun)
    {
        // Calculate exact days since last activity
        $daysSinceActivity = $this->calculateDaysSinceLastActivity($user);

        if ($daysSinceActivity < $daysInactive) {
            return; // Skip if not actually inactive enough
        }

        // Check if we already sent a retention email recently (within 7 days)
        $recentRetention = Cache::get("retention_attempt:{$user->id}:".now()->format('Y-m-d'));
        if ($recentRetention) {
            $this->info("Skipped {$user->email} - retention email already sent recently");

            return;
        }

        if ($dryRun) {
            $this->info("[DRY RUN] Would send retention email to: {$user->email} (inactive for {$daysSinceActivity} days)");

            return;
        }

        try {
            Log::info('Dispatching user inactivity retention job', [
                'user_id' => $user->id,
                'email' => $user->email,
                'days_inactive' => $daysSinceActivity,
            ]);

            // Dispatch the job to the queue
            UserInactivityJob::dispatch($user, $daysSinceActivity)
                ->onQueue('retention');

            $this->info("Dispatched retention email job for: {$user->email} (inactive for {$daysSinceActivity} days)");

        } catch (\Exception $e) {
            Log::error('Failed to dispatch user inactivity retention job', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $this->error("Failed to dispatch retention job for {$user->email}: ".$e->getMessage());
        }
    }

    private function calculateDaysSinceLastActivity($user): int
    {
        $lastActivityDate = null;

        // Check mindfulness activities
        $lastMindfulness = MindfulnessActivity::where('user_id', $user->id)
            ->orderBy('completed_at', 'desc')
            ->first();
        if ($lastMindfulness) {
            $lastActivityDate = $lastMindfulness->completed_at;
        }

        // Check mood trackings
        $lastMood = MoodTracking::where('user_id', $user->id)
            ->orderBy('logged_at', 'desc')
            ->first();
        if ($lastMood && (! $lastActivityDate || $lastMood->logged_at > $lastActivityDate)) {
            $lastActivityDate = $lastMood->logged_at;
        }

        return $lastActivityDate ? Carbon::now()->diffInDays($lastActivityDate) : PHP_INT_MAX;
    }
}
