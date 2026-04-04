<?php

namespace App\Console\Commands;

use App\Mail\Drip\Day3CheckIn;
use App\Mail\Drip\FirstSessionBadge;
use App\Mail\Drip\Month1Milestone;
use App\Mail\Drip\Week1Summary;
use App\Mail\Drip\WinbackReminder;
use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Models\UserEmailSequence;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Dispatches drip email sequences to users based on their account age
 * and activity. Each sequence step is idempotent — it records the send
 * in `user_email_sequences` to prevent duplicates.
 *
 * Schedule: php artisan emails:dispatch-drip   (run daily)
 */
class DispatchDripEmails extends Command
{
    protected $signature = 'emails:dispatch-drip {--dry-run : Log actions without sending}';

    protected $description = 'Dispatch lifecycle drip emails (welcome, day3, week1, month1, winback, first-session-badge)';

    /**
     * Drip steps keyed by sequence_key.
     * 'days_after_signup' triggers based on account age.
     * 'days_inactive'     triggers additionally based on last activity.
     * 'requires_session'  skips if user has no completed sessions.
     */
    private array $steps = [
        'welcome' => [
            'days_after_signup' => 0,
            'class' => WelcomeEmail::class,
        ],
        'day3_checkin' => [
            'days_after_signup' => 3,
            'class' => Day3CheckIn::class,
        ],
        'week1_summary' => [
            'days_after_signup' => 7,
            'class' => Week1Summary::class,
        ],
        'month1_milestone' => [
            'days_after_signup' => 30,
            'class' => Month1Milestone::class,
        ],
        'first_session_badge' => [
            'days_after_signup' => 0,        // sent when first session detected
            'requires_session' => true,
            'class' => FirstSessionBadge::class,
        ],
        'winback' => [
            'days_after_signup' => 45,
            'days_inactive' => 14,       // only if inactive for 14+ days
            'class' => WinbackReminder::class,
        ],
    ];

    private int $sent = 0;

    private int $skipped = 0;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info('Dispatching drip emails'.($dryRun ? ' [DRY RUN]' : '').'...');

        User::whereNotNull('email_verified_at')
            ->where('is_active', true)
            ->chunk(100, function ($users) use ($dryRun) {
                foreach ($users as $user) {
                    $this->processUser($user, $dryRun);
                }
            });

        $this->newLine();
        $this->info("Done — sent: {$this->sent}, skipped: {$this->skipped}");

        return Command::SUCCESS;
    }

    private function processUser(User $user, bool $dryRun): void
    {
        $accountAgeDays = Carbon::parse($user->created_at)->diffInDays(now());
        $lastActivity = $user->last_activity_at ?? $user->created_at;
        $inactiveDays = Carbon::parse($lastActivity)->diffInDays(now());

        foreach ($this->steps as $key => $step) {
            // Already sent? Skip.
            if ($this->alreadySent($user->id, $key)) {
                continue;
            }

            // Welcome email: only on day 0 (registration day)
            if ($key === 'welcome' && $accountAgeDays > 1) {
                $this->skipped++;

                continue;
            }

            // Generic day-threshold check
            $daysRequired = $step['days_after_signup'] ?? 0;
            if ($accountAgeDays < $daysRequired) {
                continue; // Not yet due
            }

            // Winback: requires inactivity threshold
            if (($step['days_inactive'] ?? null) && $inactiveDays < $step['days_inactive']) {
                continue;
            }

            // First session badge: requires at least one completed session
            if (! empty($step['requires_session'])) {
                $hasSession = $user->sessions()
                    ->whereIn('status', ['completed'])
                    ->exists();
                if (! $hasSession) {
                    continue;
                }
            }

            // Dispatch
            $this->dispatch($user, $key, $step['class'], $dryRun);
        }
    }

    private function dispatch(User $user, string $key, string $mailableClass, bool $dryRun): void
    {
        try {
            if (! $dryRun) {
                // Build the mailable — WelcomeEmail has a different constructor
                $mailable = $mailableClass === WelcomeEmail::class
                    ? new WelcomeEmail($user->first_name ?? $user->name, config('app.url').'/login')
                    : new $mailableClass($user);

                Mail::to($user->email)->queue($mailable);

                UserEmailSequence::updateOrCreate(
                    ['user_id' => $user->id, 'sequence_key' => $key],
                    ['status' => 'sent', 'sent_at' => now()],
                );
            }

            $this->sent++;
            $this->info("  → [{$key}] queued for {$user->email}".($dryRun ? ' (dry)' : ''));
        } catch (\Throwable $e) {
            Log::error("DripEmail dispatch failed [{$key}]", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function alreadySent(int $userId, string $key): bool
    {
        return UserEmailSequence::where('user_id', $userId)
            ->where('sequence_key', $key)
            ->where('status', 'sent')
            ->exists();
    }
}
