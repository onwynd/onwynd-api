<?php

namespace App\Console\Commands;

use App\Models\TherapySession;
use App\Notifications\WellbeingCheckin48h;
use Illuminate\Console\Command;

class SendWellbeingCheckin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:send-wellbeing-checkin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send wellbeing check-ins 48 hours after a session ends';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cutoff = now()->subHours(48);
        $start = (clone $cutoff)->subHour();
        $end = (clone $cutoff)->addHour();

        $sessions = TherapySession::where('status', 'completed')
            ->whereBetween('ended_at', [$start, $end])
            ->whereNull('wellbeing_checkin_sent_at')
            ->with(['patient'])
            ->get();

        $this->info("Found {$sessions->count()} sessions requiring 48h check-in.");

        foreach ($sessions as $session) {
            if ($session->patient) {
                $session->patient->notify(new WellbeingCheckin48h);
                $session->update(['wellbeing_checkin_sent_at' => now()]);
                $this->info("Check-in sent to: {$session->patient->email}");
            }
        }
    }
}
