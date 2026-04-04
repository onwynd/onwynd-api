<?php

namespace App\Console\Commands;

use App\Models\Therapist;
use App\Models\Therapy\TherapistClosedDate;
use App\Services\NotificationService\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RemoveExpiredTherapistClosedDates extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'therapist:remove-expired-closed-dates 
                            {--send-availability-notification : Notify users when therapist becomes available}
                            {--log-removed : Log all removed dates}
                            {--days=0 : Keep records for this many days before deletion}';

    /**
     * The console command description.
     */
    protected $description = 'Remove expired therapist closed dates and restore availability';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $notificationService = app(NotificationService::class);
        $sendNotification = $this->option('send-availability-notification');
        $logRemoved = $this->option('log-removed');
        $retentionDays = (int) $this->option('days');

        $this->info('🗓️  Processing expired therapist closed dates...');
        $this->newLine();

        try {
            // Determine cutoff date based on retention policy
            $cutoffDate = now()->subDays($retentionDays);

            // 1. Find expired closed dates
            $expiredClosedDates = TherapistClosedDate::where('end_date', '<', $cutoffDate)
                ->where('is_removed', false)
                ->get()
                ->groupBy('therapist_id');

            if ($expiredClosedDates->isEmpty()) {
                $this->info('✅ No expired closed dates found');

                return self::SUCCESS;
            }

            $this->info("🔍 Found {$expiredClosedDates->count()} therapists with expired closed dates");
            $totalRemoved = 0;

            $bar = $this->output->createProgressBar($expiredClosedDates->count());

            foreach ($expiredClosedDates as $therapistId => $closedDates) {
                try {
                    $therapist = Therapist::find($therapistId);

                    if (! $therapist) {
                        $this->warn("⚠️  Therapist {$therapistId} not found, skipping");

                        continue;
                    }

                    $dateCount = $closedDates->count();

                    // Store removed dates for logging before deletion
                    $removedDatesList = $closedDates->map(function ($date) {
                        return [
                            'start' => $date->start_date->format('Y-m-d'),
                            'end' => $date->end_date->format('Y-m-d'),
                            'reason' => $date->reason,
                        ];
                    })->toArray();

                    // Mark as removed (soft delete or update flag)
                    foreach ($closedDates as $closedDate) {
                        $closedDate->update([
                            'is_removed' => true,
                            'removed_at' => now(),
                        ]);
                    }

                    if ($logRemoved) {
                        Log::info("Removed {$dateCount} closed dates for therapist {$therapistId}", [
                            'therapist_name' => $therapist->user->name,
                            'removed_dates' => $removedDatesList,
                        ]);
                    }

                    // Send notification to waiting users if enabled
                    if ($sendNotification) {
                        $this->notifyWaitingUsers($therapist, $notificationService);
                    }

                    $this->line("✅ Removed {$dateCount} closed dates for therapist: {$therapist->user->name}");
                    $totalRemoved += $dateCount;

                } catch (\Exception $e) {
                    $this->error("❌ Error processing therapist {$therapistId}: {$e->getMessage()}");
                    Log::error('Error removing therapist closed dates', [
                        'therapist_id' => $therapistId,
                        'error' => $e->getMessage(),
                    ]);
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            // 2. Verify therapist availability restored
            $restoredTherapists = Therapist::whereIn('id', $expiredClosedDates->keys())
                ->where('is_available', true)
                ->count();

            // 3. Check for upcoming closed dates
            $upcomingClosedDates = TherapistClosedDate::where('start_date', '<=', now()->addDays(7))
                ->where('start_date', '>', now())
                ->where('is_removed', false)
                ->count();

            // 4. Generate summary report
            $this->info('📊 Therapist Closed Dates Summary:');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Expired dates removed', $totalRemoved],
                    ['Therapists affected', $expiredClosedDates->count()],
                    ['Therapists now available', $restoredTherapists],
                    ['Upcoming closed dates', $upcomingClosedDates],
                    ['Data retention days', $retentionDays],
                ]
            );

            // 5. Alert on upcoming closures if any
            if ($upcomingClosedDates > 0) {
                $this->info("⚠️  Note: {$upcomingClosedDates} therapists have closed dates coming up within 7 days");
                $this->displayUpcomingClosures();
            }

            // 6. Log final statistics
            $stats = [
                'total_removed' => $totalRemoved,
                'therapists_affected' => $expiredClosedDates->count(),
                'therapists_restored' => $restoredTherapists,
                'upcoming_closures' => $upcomingClosedDates,
                'retention_days' => $retentionDays,
                'timestamp' => now()->toIso8601String(),
            ];

            Log::info('Therapist closed dates cleanup completed', $stats);

            $this->info('✅ Expired therapist closed dates processing completed');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error processing closed dates: {$e->getMessage()}");
            Log::error('Therapist closed dates cleanup failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Notify users who were waiting for therapist availability
     */
    private function notifyWaitingUsers(Therapist $therapist, NotificationService $notificationService): void
    {
        // Find users who have this therapist in their preferences
        // or have been looking for similar therapist specialties
        $users = $therapist->waitlistUsers()
            ->where('is_notified', false)
            ->limit(50)
            ->get();

        foreach ($users as $user) {
            try {
                $notificationService->sendTherapistAvailabilityNotification(
                    $user,
                    $therapist,
                    'Therapist '.$therapist->user->name.' is now available again'
                );

                $user->pivot->update(['is_notified' => true]);

            } catch (\Exception $e) {
                Log::error('Failed to notify user of therapist availability', [
                    'user_id' => $user->id,
                    'therapist_id' => $therapist->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Display upcoming closed date information
     */
    private function displayUpcomingClosures(): void
    {
        $upcomingClosures = TherapistClosedDate::where('start_date', '<=', now()->addDays(7))
            ->where('start_date', '>', now())
            ->where('is_removed', false)
            ->with('therapist.user')
            ->orderBy('start_date', 'asc')
            ->limit(10)
            ->get();

        $this->newLine();
        $this->info('📅 Upcoming Therapist Closures (Next 7 days):');

        $closureData = $upcomingClosures->map(fn ($closure) => [
            $closure->therapist->user->name,
            $closure->start_date->format('Y-m-d'),
            $closure->end_date->format('Y-m-d'),
            $closure->reason ?? 'N/A',
        ])->toArray();

        if ($closureData) {
            $this->table(
                ['Therapist', 'Start Date', 'End Date', 'Reason'],
                $closureData
            );
        }
    }
}
