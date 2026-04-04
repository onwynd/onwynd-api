<?php

namespace App\Console\Commands;

use App\Mail\AbandonedBookingEmail;
use App\Models\BookingIntent;
use App\Models\TherapySession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckAbandonedBookings extends Command
{
    protected $signature   = 'bookings:check-abandoned';
    protected $description = 'Send one recovery email for payment-initiated booking intents abandoned >2h ago';

    // Only send during these hours (WAT = UTC+1) — respect sleep hours
    private const SEND_HOUR_START = 8;
    private const SEND_HOUR_END   = 20;

    public function handle(): int
    {
        $nowWat = now()->setTimezone('Africa/Lagos');

        if ($nowWat->hour < self::SEND_HOUR_START || $nowWat->hour >= self::SEND_HOUR_END) {
            $this->info('Outside send window — skipping.');
            return self::SUCCESS;
        }

        // Only chase high-intent abandonment: user got far enough to initiate payment
        /** @var \Illuminate\Database\Eloquent\Collection<int, BookingIntent> $intents */
        $intents = BookingIntent::with('user')
            ->whereIn('stage', ['payment_initiated', 'therapist_selected'])
            ->whereNull('completed_at')
            ->whereNull('abandoned_email_sent_at')
            ->where('created_at', '<=', now()->subHours(2))
            ->where('created_at', '>=', now()->subDays(3)) // don't chase cold intents
            ->get();

        $sent = 0;

        foreach ($intents as $intent) {
            $user = $intent->user;

            if (! $user || ! $user->email) {
                continue;
            }

            // Skip anonymous placeholder emails
            if (str_ends_with($user->email, '@anonymous.onwynd.com')) {
                continue;
            }

            // Skip if the user has booked a session since this intent was created —
            // they completed a booking on another device or through a different flow
            $bookedSince = TherapySession::where('patient_id', $user->id)
                ->where('created_at', '>=', $intent->created_at)
                ->exists();

            if ($bookedSince) {
                // Close the intent silently
                $intent->update(['completed_at' => now()]);
                continue;
            }

            try {
                Mail::to($user->email)->queue(new AbandonedBookingEmail($intent));
                $intent->update(['abandoned_email_sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                $this->error("Failed to queue for user {$user->id}: {$e->getMessage()}");
            }
        }

        $this->info("Abandoned booking emails queued: {$sent}");

        return self::SUCCESS;
    }
}
