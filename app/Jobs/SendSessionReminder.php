<?php

namespace App\Jobs;

use App\Models\TherapySession;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSessionReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public TherapySession $session)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notification): void
    {
        // Ensure we load the necessary relationships
        $this->session->loadMissing(['patient', 'therapist.user']);

        $user = $this->session->patient;
        $therapist = $this->session->therapist;
        $time = $this->session->scheduled_at->format('h:i A');

        // Notify Patient
        if ($user && $user->phone) {
            $notification->sendSessionReminder(
                $user->phone,
                $therapist && $therapist->user ? $therapist->user->first_name : 'Your Therapist',
                $time
            );
        }

        // Notify Therapist via WhatsApp
        if ($therapist && $therapist->user && $therapist->user->phone) {
            $notification->sendWhatsAppMessage(
                $therapist->user->phone,
                'Onwynd Reminder: Your session with '.($user->first_name ?? 'your patient')." starts in 15 mins ($time). Join here: ".config('app.dashboard_url')."/therapist/sessions/{$this->session->uuid}/room"
            );
        }
    }
}
