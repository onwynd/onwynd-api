<?php

namespace App\Console\Commands;

use App\Mail\SessionCheckIn;
use App\Models\TherapySession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSessionCheckins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:send-session-checkins';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send check-in emails to users 3 days after their session';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting session check-in process...');

        // Find sessions completed 3 days ago (between 72 and 96 hours ago to be safe, or just < 3 days and not sent)
        // We'll look for sessions ended between 3 and 4 days ago to avoid sending to very old sessions if the cron failed for a while
        // Actually, safer to just check `checkin_sent_at` is null and ended_at < 3 days ago.
        // But let's limit it to recent past (e.g. within last 7 days) to avoid spamming old sessions upon first deployment.

        $threeDaysAgo = now()->subDays(3);
        $sevenDaysAgo = now()->subDays(7);

        $sessions = TherapySession::where('status', 'completed')
            ->whereNull('checkin_sent_at')
            ->where('ended_at', '<=', $threeDaysAgo)
            ->where('ended_at', '>=', $sevenDaysAgo)
            ->with(['patient', 'therapist'])
            ->get();

        $this->info("Found {$sessions->count()} sessions requiring check-in.");

        foreach ($sessions as $session) {
            try {
                if (! $session->patient) {
                    continue;
                }

                Mail::to($session->patient->email)->send(new SessionCheckIn(
                    $session->patient->name,
                    $session->therapist->name ?? 'your therapist',
                    url('/patient/dashboard/sessions/'.$session->id)
                ));

                $session->update(['checkin_sent_at' => now()]);

                $this->info("Check-in sent to: {$session->patient->email}");

            } catch (\Exception $e) {
                $this->error("Failed to send check-in for session {$session->id}: ".$e->getMessage());
                Log::error('Session Check-in Error: '.$e->getMessage());
            }
        }

        $this->info('Session check-in process completed.');
    }
}
