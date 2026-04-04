<?php

namespace App\Listeners;

use App\Events\SessionCompleted;
use App\Mail\SessionSummary;
use App\Services\NotificationService\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSessionCompletionNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * @var NotificationService
     */
    private $notificationService;

    /**
     * Create the event listener
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event
     */
    public function handle(SessionCompleted $event): void
    {
        try {
            $session = $event->session;

            Log::info('Processing session completion', ['session_id' => $session->id]);

            // 1. Send standard notifications via service
            $this->notificationService->sendSessionCompletionNotification($session->user);
            $this->notificationService->sendSessionCompletionNotification($session->therapist);

            // 2. Send Detailed Session Summary Email to Patient
            // We assume the session model has these fields or relations
            // If strictly not present, we use safe defaults to ensure email sends
            $summary = $session->summary ?? 'Session completed successfully.';
            $recommendations = $session->recommendations ?? []; // Should be array
            $homework = $session->homework ?? []; // Should be array

            Mail::to($session->user->email)->send(new SessionSummary(
                $session->user->name,
                $session->therapist->name ?? 'Therapist',
                $session->scheduled_at ? $session->scheduled_at->toFormattedDateString() : now()->toFormattedDateString(),
                $summary,
                $recommendations,
                $homework,
                url('/patient/dashboard/sessions/'.$session->id)
            ));

            Log::info('Session summary email sent to user: '.$session->user->id);

        } catch (\Exception $e) {
            Log::error('Failed to send session completion notification/email', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
