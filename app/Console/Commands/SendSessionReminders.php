<?php

namespace App\Console\Commands;

use App\Models\TherapySession;
use App\Notifications\SessionReminder;
use App\Services\NotificationService\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Send therapy session reminders (in-app + SMS + WhatsApp).
 *
 * Called by the scheduler at each configured interval.
 * The reminder schedule is driven by the `reminder_schedule_session`
 * DB setting (comma-separated minutes, e.g. "1440,60" = 24h and 1h before).
 *
 * The scheduler calls this with --minutes=N; it finds all sessions
 * that start within a ±5-min window of now+N minutes.
 *
 * Usage:
 *   php artisan sessions:send-reminders --minutes=1440
 *   php artisan sessions:send-reminders --minutes=60
 */
class SendSessionReminders extends Command
{
    protected $signature = 'sessions:send-reminders
                            {--hours=   : Reminder timeframe in hours}
                            {--minutes= : Reminder timeframe in minutes}
                            {--from-schedule : Auto-run all intervals from DB reminder_schedule_session setting}';

    protected $description = 'Send therapy session reminders via in-app, SMS, and WhatsApp';

    public function handle(NotificationService $notificationService): void
    {
        if ($this->option('from-schedule')) {
            $this->runFromDbSchedule($notificationService);
            return;
        }

        $hours   = $this->option('hours');
        $minutes = $this->option('minutes');

        if (! $hours && ! $minutes) {
            $this->error('Specify --hours, --minutes, or --from-schedule');
            return;
        }

        $offsetMinutes = $hours ? ($hours * 60) : (int) $minutes;
        $this->sendForOffset($offsetMinutes, $notificationService);
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function runFromDbSchedule(NotificationService $notificationService): void
    {
        $raw = DB::table('settings')
            ->where('group', 'sms')
            ->where('key', 'reminder_schedule_session')
            ->value('value') ?? '1440,60';

        foreach (array_filter(array_map('intval', explode(',', $raw))) as $offsetMins) {
            $this->sendForOffset($offsetMins, $notificationService);
        }
    }

    private function sendForOffset(int $offsetMinutes, NotificationService $notificationService): void
    {
        $target = now()->addMinutes($offsetMinutes);
        $start  = (clone $target)->subMinutes(5);
        $end    = (clone $target)->addMinutes(5);

        $sessions = TherapySession::where('status', 'booked')
            ->whereBetween('scheduled_at', [$start, $end])
            ->with(['patient', 'therapist'])
            ->get();

        $label = $offsetMinutes >= 60
            ? round($offsetMinutes / 60).'h'
            : $offsetMinutes.'min';

        $this->info("Offset {$label}: found {$sessions->count()} session(s).");

        foreach ($sessions as $session) {
            $cacheKey = "session_reminder_{$offsetMinutes}_{$session->id}";
            if (Cache::has($cacheKey)) {
                continue;
            }

            $vars = [
                'therapist_name' => $session->therapist?->full_name
                    ?? $session->therapist?->name
                    ?? 'your therapist',
                'date' => $session->scheduled_at->format('l, M j'),
                'time' => $session->scheduled_at->format('g:i A'),
            ];

            // ── Patient ──────────────────────────────────────────────────────
            if ($session->patient) {
                $patient = $session->patient;

                // In-app
                $patient->notify(new SessionReminder($session, $label));

                // SMS + WhatsApp
                if ($patient->phone) {
                    $notificationService->sendSessionReminder(
                        $patient->phone,
                        array_merge($vars, ['name' => $patient->first_name ?? 'there']),
                        $patient->id
                    );
                }
            }

            // ── Therapist ────────────────────────────────────────────────────
            $therapist = $session->therapist;
            if ($therapist) {
                $therapist->notify(new SessionReminder($session, $label));

                if ($therapist->phone) {
                    $notificationService->sendSessionReminder(
                        $therapist->phone,
                        array_merge($vars, [
                            'name'           => $therapist->first_name ?? 'there',
                            'therapist_name' => $session->patient?->full_name
                                ?? $session->patient?->name
                                ?? 'your patient',
                        ]),
                        $therapist->id
                    );
                }
            }

            Cache::put($cacheKey, true, now()->addMinutes(30));
            $this->info("  Sent {$label} reminder for session #{$session->id}");
        }
    }
}
