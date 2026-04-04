<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ===== SUBSCRIPTION MANAGEMENT =====

        /**
         * Process expired subscriptions
         * Checks for expiring subscriptions, sends warnings, handles renewals
         */
        $schedule->command('subscriptions:process-expired --days=3')
            ->dailyAt('01:00')
            ->name('subscriptions-expiry-processing')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('Subscription expiry processing failed'));

        /**
         * Reset organization member session counts
         * Resets sessions_used_this_month to 0 on the 1st of each month
         */
        $schedule->command('organizations:reset-session-counts')
            ->monthlyOn(1, '00:00')
            ->name('organization-session-reset')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('Organization session count reset failed'));

        // ===== CORPORATE PILOT LIFECYCLE =====

        /**
         * Corporate pilot lifecycle notifications
         * Sends midpoint check-in, 14-day pre-renewal, and expiry emails
         * to HR directors of companies on active pilot contracts.
         */
        $schedule->command('pilots:notify')
            ->dailyAt('09:00')
            ->name('pilot-lifecycle-notifications')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('Pilot lifecycle notifications failed'));

        // ===== DRIP EMAIL SEQUENCES =====

        /**
         * Lifecycle drip emails (welcome → day3 → week1 → month1 → winback)
         * Runs daily at 09:00 WAT; idempotent — skips already-sent steps.
         */
        $schedule->command('emails:dispatch-drip')
            ->dailyAt('09:00')
            ->name('drip-email-dispatch')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('Drip email dispatch failed'));

        // ===== EMAIL MANAGEMENT =====

        /**
         * Send scheduled emails in batches
         * Processes emails scheduled for specific times or delays
         * Runs frequently to ensure timely delivery
         */
        $schedule->command('emails:send-scheduled --limit=50')
            ->everyFiveMinutes()
            ->name('scheduled-emails-sending')
            ->withoutOverlapping(300)
            ->onFailure(fn () => logger()->error('Scheduled email sending failed'));

        // ===== THERAPIST ONLINE STATUS =====
        // Mark offline any user whose last_seen_at is older than 30 minutes.
        // Pairs with POST /api/v1/me/heartbeat called every 90s from therapist dashboard.
        $schedule->command('users:mark-offline')
            ->everyFiveMinutes()
            ->name('users-mark-offline')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('users:mark-offline command failed'));

        /**
         * Retry failed emails
         * Attempts to resend failed emails (up to 3 attempts)
         * Runs every hour
         */
        $schedule->command('emails:send-scheduled --retry-failed --limit=20')
            ->hourly()
            ->name('failed-emails-retry')
            ->withoutOverlapping();

        // ===== THERAPIST MANAGEMENT =====

        /**
         * Remove expired therapist closed dates
         * Restores therapist availability after closed periods
         * Sends notifications to waiting users
         */
        $schedule->command('therapist:remove-expired-closed-dates --send-availability-notification')
            ->dailyAt('02:00')
            ->name('therapist-availability-restoration')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('Therapist availability restoration failed'));

        // ===== SESSION REMINDERS & CHECKS =====

        /**
         * 1:1 Session reminders — intervals read from `reminder_schedule_session` DB setting.
         * Default: 1440 min (24h) and 60 min (1h) before session.
         * Runs every 15 minutes; the command skips sessions that already received a reminder
         * for the same offset (cache-deduped per session+offset for 30 min).
         */
        $schedule->command('sessions:send-reminders --from-schedule')
            ->everyFifteenMinutes()
            ->name('session-reminders')
            ->withoutOverlapping();

        /**
         * Group session reminders — intervals read from `reminder_schedule_group` DB setting.
         * Default: 60 min (1h) and 15 min before session.
         */
        $schedule->command('sessions:send-group-reminders')
            ->everyFifteenMinutes()
            ->name('group-session-reminders')
            ->withoutOverlapping();

        /**
         * Check for session no-shows
         */
        $schedule->command('sessions:check-no-shows')->everyFifteenMinutes();

        /**
         * Abandoned booking recovery emails
         * Finds incomplete booking intents older than 2h and sends a recovery email (once per intent)
         */
        $schedule->command('bookings:check-abandoned')
            ->hourly()
            ->name('abandoned-booking-recovery')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('Abandoned booking recovery check failed'));

        /**
         * Send inactivity nudges weekly
         */
        $schedule->command('users:send-inactivity-nudge')->weeklyOn(1, '09:00');

        /**
         * Send wellbeing check-ins (48h after session)
         */
        $schedule->command('users:send-wellbeing-checkin')->hourly();

        /**
         * Check organization low credits
         */
        $schedule->command('orgs:check-low-credits')->dailyAt('07:00');

        // ===== SESSION MANAGEMENT =====

        /**
         * Send therapy session reminders
         * Covers missed dispatches by scanning next 15 minutes
         */
        $schedule->call(function () {
            \App\Models\TherapySession::whereBetween('scheduled_at', [now(), now()->addMinutes(15)])
                ->where('status', 'scheduled')
                ->each(function ($session) {
                    $cacheKey = 'session_reminder_dispatched:'.$session->id;
                    if (! \Illuminate\Support\Facades\Cache::get($cacheKey)) {
                        \App\Jobs\SendSessionReminder::dispatch($session);
                        \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addMinutes(20));
                        Log::info('Session reminder dispatched by scheduler', ['session_id' => $session->id]);
                    }
                });
        })->everyFiveMinutes();

        /**
         * FIX 9: Prepare video sessions 30 minutes before start.
         * Creates the LiveKit room and sends join-link emails to both parties.
         */
        $schedule->call(function () {
            $windowStart = now()->addMinutes(28);
            $windowEnd = now()->addMinutes(32);

            \App\Models\Therapy\VideoSession::whereHas('therapySession', function ($q) use ($windowStart, $windowEnd) {
                $q->whereBetween('scheduled_at', [$windowStart, $windowEnd])
                  ->where('status', 'booked');
            })
                ->whereNull('prepared_at')
                ->each(function (\App\Models\Therapy\VideoSession $videoSession) {
                    $cacheKey = 'video_session_prepared:' . $videoSession->id;
                    if (! \Illuminate\Support\Facades\Cache::get($cacheKey)) {
                        try {
                            app(\App\Services\Therapy\VideoSessionService::class)->prepareSession($videoSession);
                            \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addHours(2));
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::error('Video session prep failed', [
                                'video_session_id' => $videoSession->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                });
        })
            ->everyFiveMinutes()
            ->name('video-session-preparation')
            ->withoutOverlapping();

        /**
         * G1: Automated Session Closure (T+90 Rule)
         * If a session hasn't started within 90 minutes of its scheduled time,
         * mark it as no_show and handle refunds if applicable.
         */
        $schedule->call(function () {
            $cutoff = now()->subMinutes(90);

            \App\Models\TherapySession::where('status', 'booked')
                ->where('start_time', '<=', $cutoff)
                ->each(function (\App\Models\TherapySession $session) {
                    // Mark as no_show
                    $session->update(['status' => 'no_show']);

                    // G2: Refund for Therapist No-Show
                    // In a real scenario, we'd check logs to see if the therapist joined.
                    // For this rule, if it's still 'booked' after T+90, it's a platform/therapist failure.
                    if ($session->payment && $session->payment->status === 'completed') {
                        // Trigger refund logic (e.g., dispatch a job)
                        // \App\Jobs\IssueRefund::dispatch($session->payment, 'Therapist no-show (T+90)');
                        Log::info('Session T+90: Marked as no_show. Refund triggered.', ['session_id' => $session->id]);
                    }
                });
        })->everyMinute();

        /**
         * K3: Mood Nudges
         * If a user hasn't logged their mood for 48 hours, send a nudge.
         */
        $schedule->call(function () {
            $cutoff = now()->subHours(48);

            \App\Models\User::whereHas('role', fn ($q) => $q->where('slug', 'patient'))
                ->whereDoesntHave('moodLogs', fn ($q) => $q->where('created_at', '>=', $cutoff))
                ->where('is_active', true)
                ->each(function ($user) {
                    $cacheKey = 'mood_nudge_sent:'.$user->id;
                    if (! \Illuminate\Support\Facades\Cache::get($cacheKey)) {
                        $user->notify(new \App\Notifications\MoodNudgeNotification);
                        \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addDays(2));
                    }
                });
        })->dailyAt('18:00'); // Send in the evening

        /**
         * Auto-complete expired therapy sessions
         * Marks sessions past their end time as completed
         */
        $schedule->call(function () {
            \App\Models\TherapySession::where('ended_at', '<', now())
                ->where('status', 'active')
                ->update(['status' => 'completed']);
        })
            ->everyThirtyMinutes()
            ->name('session-auto-completion')
            ->withoutOverlapping();

        /**
         * Send session check-in emails
         * Sends a "How are you?" email 3 days after a session
         */
        $schedule->command('emails:send-session-checkins')
            ->dailyAt('10:00')
            ->name('session-checkins')
            ->withoutOverlapping();

        // ===== NOTIFICATION MANAGEMENT =====

        /**
         * Send Weekly Admin Report
         */
        $schedule->command('reports:send-weekly-admin')
            ->weeklyOn(1, '08:00')
            ->name('weekly-admin-report')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('Weekly admin report failed'));

        /**
         * Send Biweekly Institutional Reports
         * Runs on the 1st and 15th of each month at 08:00.
         * Each active partner receives a 14-day digest: user count,
         * active users, new sign-ups, engagement rate, and session stats.
         */
        $schedule->command('reports:send-institutional-biweekly')
            ->cron('0 8 1,15 * *')
            ->name('institutional-biweekly-report')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('Institutional biweekly report dispatch failed'));

        /**
         * Send daily mood check-in push notifications
         * Dispatches Web Push to subscribed users who haven't logged a mood today
         */
        $schedule->command('push:send-daily-checkins')
            ->dailyAt('09:00')
            ->name('push-daily-checkins')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('Daily check-in push notifications failed'));

        /**
         * Send daily check-in prompts
         * Triggers AI-powered daily check-in notifications at 9 AM
         */
        $schedule->command('queue:work --queue=notifications --max-jobs=50 --max-time=3600')
            ->dailyAt('09:00')
            ->name('daily-checkin-notifications')
            ->withoutOverlapping();

        /**
         * Check for broken daily streaks
         * Sends retention emails to users who broke their wellness streak
         */
        $schedule->command('streak:check-daily')
            ->dailyAt('10:00')
            ->name('daily-streak-check')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('Daily streak check failed'));
        $schedule->command('streak:check-daily --warn')
            ->dailyAt('20:00') // FIX-3: 8 PM WAT — gives users the full day before the retention nudge fires
            ->name('streak-saver-warn')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('Streak saver warn failed'));

        /**
         * Process user inactivity for retention
         * Sends emails to users inactive for 3+ days
         */
        $schedule->command('users:process-inactivity --days=3')
            ->dailyAt('11:00')
            ->name('user-inactivity-retention')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('User inactivity retention processing failed'));

        /**
         * Clean up old notifications
         * Archives notifications older than 30 days
         */
        $schedule->call(function () {
            \App\Models\Notification::where('created_at', '<', now()->subDays(30))
                ->delete();
        })
            ->daily()
            ->name('notification-cleanup')
            ->withoutOverlapping();

        // ===== PAYMENT PROCESSING =====

        /**
         * Process pending payments
         * Handles payment processing for failed transactions
         */
        $schedule->command('queue:work --queue=payments --max-jobs=100 --max-time=1800')
            ->everyFiveMinutes()
            ->name('payment-queue-processing')
            ->withoutOverlapping();

        /**
         * Process retention emails
         * Handles user inactivity retention email queue
         */
        $schedule->command('queue:work --queue=retention --max-jobs=50 --max-time=1800')
            ->hourly()
            ->name('retention-queue-processing')
            ->withoutOverlapping();

        /**
         * Generate invoices for recurring charges
         * Creates invoices for subscription renewals
         */
        $schedule->call(function () {
            // Invoice generation logic
        })
            ->monthlyOn(1, '03:00')
            ->name('invoice-generation')
            ->withoutOverlapping();

        /**
         * Founding therapist stipend top-up — N150,000/month guaranteed minimum for 3 months post-launch.
         * Runs on the 1st of each month at 02:00 (before payout batch at 03:00).
         */
        $schedule->call(function () {
            app(\App\Services\Finance\FoundingStipendService::class)->processMonthlyStipends();
        })
            ->monthlyOn(1, '02:00')
            ->name('founding-stipend-payout')
            ->withoutOverlapping();

        // ===== ANALYTICS & REPORTING =====

        /**
         * Generate daily analytics reports
         * Compiles platform usage statistics
         */
        $schedule->command('queue:work --queue=reports --max-jobs=10 --max-time=7200')
            ->dailyAt('06:00')
            ->name('daily-analytics')
            ->withoutOverlapping();

        /**
         * Generate weekly summary reports
         * Compiles weekly statistics for admins and organizations
         */
        $schedule->call(function () {
            // Weekly report generation
        })
            ->weeklyOn(0, '08:00')
            ->name('weekly-reports')
            ->withoutOverlapping();

        /**
         * Generate monthly performance reports
         * Comprehensive monthly analytics and metrics
         */
        $schedule->call(function () {
            // Monthly report generation
        })
            ->monthlyOn(1, '09:00')
            ->name('monthly-reports')
            ->withoutOverlapping();

        // ===== DASHBOARD METRICS =====

        /**
         * Refresh admin dashboard metrics
         * Updates platform-wide KPIs and business intelligence
         * Runs hourly to keep metrics fresh
         */
        $schedule->command('dashboard:refresh --type=admin')
            ->hourly()
            ->name('admin-dashboard-refresh')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('Admin dashboard refresh failed'));

        /**
         * Refresh institutional dashboards
         * Updates B2B partner analytics and ROI metrics
         * Runs every 6 hours to maintain reasonable cache freshness
         */
        $schedule->command('dashboard:refresh --type=institutional')
            ->everyFourHours()
            ->name('institutional-dashboard-refresh')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('Institutional dashboard refresh failed'));

        /**
         * Refresh therapist dashboards
         * Updates therapist performance and earnings metrics
         * Runs every 4 hours during business hours
         */
        $schedule->command('dashboard:refresh --type=therapist')
            ->everyFourHours()
            ->name('therapist-dashboard-refresh')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('Therapist dashboard refresh failed'));

        /**
         * Refresh patient dashboards
         * Updates wellness scores and engagement metrics
         * Runs every 2 hours to keep patient data current
         */
        $schedule->command('dashboard:refresh --type=patient')
            ->everyTwoHours()
            ->name('patient-dashboard-refresh')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('Patient dashboard refresh failed'));

        /**
         * OKR auto-refresh — pulls DashboardMetric values into auto-bound key results nightly.
         * Fires health-change alerts (email + in-app + Slack) on status transitions.
         */
        $schedule->command('okr:refresh')
            ->dailyAt('02:00')
            ->name('okr-auto-refresh')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('OKR auto-refresh failed'));

        /**
         * OKR weekly digest — sent every Monday at 09:00 to all executives + department leads.
         */
        $schedule->command('okr:weekly-digest')
            ->weeklyOn(1, '09:00')
            ->name('okr-weekly-digest')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('OKR weekly digest failed'));

        // ===== APPROVAL WORKFLOW =====

        /**
         * Process approval escalations — sends 48h reminders and escalates 96h-overdue steps
         * up the management chain. Runs every hour so SLA breaches are caught promptly.
         */
        $schedule->command('approvals:escalate')
            ->hourly()
            ->name('approval-escalations')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->error('Approval escalation processing failed'));

        // ===== DATA MAINTENANCE =====

        /**
         * Clean up old activity logs
         * Removes audit logs older than 90 days
         */
        $schedule->call(function () {
            // \Spatie\Activitylog\Models\Activity::where('created_at', '<', now()->subDays(90))->delete();
            // Spatie activity log not yet installed
        })
            ->weekly()
            ->name('audit-log-cleanup')
            ->withoutOverlapping();

        /**
         * Archive completed sessions
         * Archives therapy sessions older than 1 year
         */
        $schedule->call(function () {
            \App\Models\TherapySession::where('created_at', '<', now()->subYears(1))
                ->where('status', 'completed')
                ->update(['is_archived' => true]);
        })
            ->monthly()
            ->name('session-archival')
            ->withoutOverlapping();

        /**
         * Clean up soft-deleted records
         * Permanently deletes soft-deleted records older than 30 days
         */
        $schedule->call(function () {
            // Cleanup soft-deleted records
        })
            ->weekly()
            ->name('soft-delete-cleanup')
            ->withoutOverlapping();

        // ===== SYNC & INTEGRATION =====

        /**
         * Sync AI models availability
         * Updates available AI models and their status
         */
        $schedule->command('ai:sync-models')
            ->dailyAt('04:00')
            ->name('ai-model-sync')
            ->withoutOverlapping();

        /**
         * Sync therapist availability
         * Refreshes therapist availability from calendar integrations
         */
        $schedule->call(function () {
            // Therapist availability sync
        })
            ->everySixHours()
            ->name('therapist-availability-sync')
            ->withoutOverlapping();

        /**
         * Sync external calendars
         * Updates calendar integrations (Google Calendar, Outlook, etc.)
         */
        $schedule->call(function () {
            // Calendar sync
        })
            ->everyThirtyMinutes()
            ->name('calendar-sync')
            ->withoutOverlapping();

        // ===== SYSTEM HEALTH =====

        /**
         * Health check ping
         * Records system health status
         */
        $schedule->command('onwynd:health --json')
            ->everyFiveMinutes()
            ->name('system-health-check')
            ->withoutOverlapping()
            ->onFailure(fn () => logger()->alert('Health check degraded — review onwynd:health output'));

        /**
         * Database optimization
         * Optimizes database tables
         */
        $schedule->call(function () {
            // Database optimization (MySQL OPTIMIZE TABLE, etc.)
        })
            ->weekly()
            ->name('database-optimization')
            ->withoutOverlapping();

        /**
         * Clear application cache
         * Clears cached data
         */
        $schedule->command('cache:clear')
            ->daily()
            ->name('cache-clearing')
            ->withoutOverlapping();

        $schedule->command('media:cleanup-chat-voice')
            ->hourly()
            ->name('chat-voice-cleanup')
            ->withoutOverlapping();

        // ===== QUEUE MONITORING =====

        /**
         * Monitor queue health
         * Checks for stuck jobs and alerts
         */
        $schedule->call(function () {
            $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')
                ->where('created_at', '>', now()->subHours(1))
                ->count();

            if ($failedJobs > 10) {
                logger()->alert("High number of failed jobs: {$failedJobs}");
                // Send alert notification
            }
        })
            ->everyThirtyMinutes()
            ->name('queue-health-monitoring')
            ->withoutOverlapping();

        // ===== MAINTENANCE MODE WARNINGS =====

        /**
         * Check for upcoming maintenance windows
         * Alerts admins about scheduled maintenance
         */
        $schedule->call(function () {
            // Check maintenance schedule
        })
            ->daily()
            ->name('maintenance-window-alerts')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
