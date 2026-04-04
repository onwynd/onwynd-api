<?php

namespace App\Console\Commands;

use App\Models\TherapySession;
use App\Notifications\SessionNoShowPatient;
use App\Notifications\SessionNoShowTherapist;
use Illuminate\Console\Command;

class CheckNoShows extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:check-no-shows';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark therapy sessions as no-shows if neither party joined within 15 minutes of scheduled time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Patient no-show: therapist joined but patient did not within 15 minutes
        // 2. Therapist no-show: patient joined but therapist did not within 15 minutes
        // 3. Both no-show: neither party joined within 15 minutes

        $cutoff = now()->subMinutes(15);

        $sessions = TherapySession::where('status', 'scheduled')
            ->where('scheduled_at', '<=', $cutoff)
            ->whereNull('started_at')
            ->with(['patient', 'therapist.user'])
            ->get();

        $this->info("Found {$sessions->count()} sessions requiring no-show check.");

        foreach ($sessions as $session) {
            // Check if therapist joined (using some presence/log mechanism, assuming `therapist_joined_at`)
            $therapistJoined = $session->therapist_joined_at !== null;
            $patientJoined = $session->patient_joined_at !== null;

            if (! $therapistJoined && ! $patientJoined) {
                // Both no-show
                $session->update(['status' => 'no_show']);
                $session->patient->notify(new SessionNoShowPatient($session));
                $this->info("Session #{$session->id} marked as both no-show.");
            } elseif ($therapistJoined && ! $patientJoined) {
                // Patient no-show
                $session->update(['status' => 'no_show_patient']);
                $session->patient->notify(new SessionNoShowPatient($session));
                $this->info("Session #{$session->id} marked as patient no-show.");
            } elseif (! $therapistJoined && $patientJoined) {
                // Therapist no-show
                $session->update(['status' => 'no_show_therapist']);
                $session->patient->notify(new SessionNoShowTherapist($session));
                $this->info("Session #{$session->id} marked as therapist no-show.");
            }
        }
    }
}
