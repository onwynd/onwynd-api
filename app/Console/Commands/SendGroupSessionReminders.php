<?php

namespace App\Console\Commands;

use App\Mail\GroupSessionReminderMail;
use App\Models\GroupSession;
use App\Notifications\GroupSessionReminder;
use App\Services\NotificationService\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Send group session reminders (in-app + SMS + WhatsApp).
 *
 * Reminder intervals are driven by the `reminder_schedule_group` DB setting
 * (comma-separated minutes, e.g. "60,15" = 1h and 15min before).
 *
 * Usage:
 *   php artisan sessions:send-group-reminders
 */
class SendGroupSessionReminders extends Command
{
    protected $signature   = 'sessions:send-group-reminders';
    protected $description = 'Send group session reminders via in-app, SMS, and WhatsApp';

    public function handle(NotificationService $notificationService): void
    {
        $raw = DB::table('settings')
            ->where('group', 'sms')
            ->where('key', 'reminder_schedule_group')
            ->value('value') ?? '1440,30'; // 24 hours and 30 minutes before

        $offsets = array_filter(array_map('intval', explode(',', $raw)));

        foreach ($offsets as $offsetMins) {
            $this->sendForOffset($offsetMins, $notificationService);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function sendForOffset(int $offsetMinutes, NotificationService $notificationService): void
    {
        $target = now()->addMinutes($offsetMinutes);
        $start  = (clone $target)->subMinutes(5);
        $end    = (clone $target)->addMinutes(5);

        $label = $offsetMinutes >= 60
            ? round($offsetMinutes / 60).'h'
            : $offsetMinutes.'min';

        $sessions = GroupSession::where('status', 'scheduled')
            ->whereBetween('scheduled_at', [$start, $end])
            ->with(['participants', 'therapist'])
            ->get();

        $this->info("Offset {$label}: found {$sessions->count()} group session(s).");

        foreach ($sessions as $session) {
            $cacheKey = "group_reminder_{$offsetMinutes}_{$session->id}";
            if (Cache::has($cacheKey)) {
                continue;
            }

            $vars = [
                'group_name' => $session->title ?? 'Group Session',
                'date'       => $session->scheduled_at->format('l, M j'),
                'time'       => $session->scheduled_at->format('g:i A'),
            ];

            $participants = $session->participants ?? collect();

            // ── Participants (in-app + SMS + WhatsApp) ────────────────────────
            if ($participants->isNotEmpty()) {
                Notification::send($participants, new GroupSessionReminder($session, $label));

                foreach ($participants as $participant) {
                    if ($participant->phone) {
                        $notificationService->sendGroupSessionReminder(
                            $participant->phone,
                            array_merge($vars, ['name' => $participant->first_name ?? 'there']),
                            $participant->id
                        );
                    }
                }
            }

            // ── Therapist ─────────────────────────────────────────────────────
            if ($session->therapist) {
                $therapist = $session->therapist;
                $therapist->notify(new GroupSessionReminder($session, $label));

                if ($therapist->phone) {
                    $notificationService->sendGroupSessionReminder(
                        $therapist->phone,
                        array_merge($vars, ['name' => $therapist->first_name ?? 'there']),
                        $therapist->id
                    );
                }
            }

            // ── Guest email participants (no account yet — e.g. couple's partner) ──
            // These are pivot records with guest_email set but no user_id.
            // They receive no in-app or SMS notification, so email is the only channel.
            $guestRows = DB::table('group_session_participants')
                ->where('group_session_id', $session->id)
                ->whereNotNull('guest_email')
                ->whereNull('user_id')
                ->whereIn('invite_status', ['accepted', 'pending'])
                ->get();

            foreach ($guestRows as $guest) {
                $guestCacheKey = "group_reminder_guest_{$offsetMinutes}_{$guest->id}";
                if (Cache::has($guestCacheKey)) {
                    continue;
                }

                Mail::to($guest->guest_email)->send(
                    new GroupSessionReminderMail(
                        session: $session,
                        recipientName: $guest->guest_name,
                        label: $label,
                        inviteToken: $guest->invite_token,
                    )
                );

                Cache::put($guestCacheKey, true, now()->addMinutes(30));
            }

            Cache::put($cacheKey, true, now()->addMinutes(30));
            $this->info("  Sent {$label} reminder for group session #{$session->id} ({$session->title})");
        }
    }
}
