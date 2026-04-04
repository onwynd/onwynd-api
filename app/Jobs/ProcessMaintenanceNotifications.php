<?php

namespace App\Jobs;

use App\Mail\MaintenanceNotification;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ProcessMaintenanceNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $schedule;

    /**
     * Create a new job instance.
     */
    public function __construct(MaintenanceSchedule $schedule)
    {
        $this->schedule = $schedule;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // In a real production environment, we would batch this or use a notification service.
        // For this implementation, we will chunk the users and send emails.

        // Notify all users (Patient, Therapist, etc.)
        User::chunk(100, function ($users) {
            foreach ($users as $user) {
                // Skip if user has no email or is deleted
                if (! $user->email) {
                    continue;
                }

                try {
                    Mail::to($user->email)->send(new MaintenanceNotification($this->schedule));
                } catch (\Exception $e) {
                    // Log error but continue
                    \Log::error("Failed to send maintenance email to {$user->email}: ".$e->getMessage());
                }
            }
        });
    }
}
