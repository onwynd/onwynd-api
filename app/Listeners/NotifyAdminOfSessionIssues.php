<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyAdminOfSessionIssues implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $session = $event->session;
        $issueType = (new \ReflectionClass($event))->getShortName();

        $patientId = $session->patient_id ?? 'Unknown';
        $anonymisedPatient = "Patient #{$patientId}";

        $data = [
            'session_id' => $session->id,
            'session_uuid' => $session->uuid,
            'therapist_name' => $session->therapist->full_name ?? 'Unknown',
            'patient' => $anonymisedPatient,
            'duration' => $session->actual_duration_minutes ?? 0,
            'issue' => $issueType,
            'review_link' => config('app.frontend_url')."/admin/sessions/{$session->uuid}/review",
        ];

        try {
            Mail::raw("Session Issue Detected: {$issueType}\n\n".
                "Session ID: {$data['session_id']}\n".
                "Therapist: {$data['therapist_name']}\n".
                "Patient: {$data['patient']}\n".
                "Actual Duration: {$data['duration']} mins\n".
                "Review Link: {$data['review_link']}",
                function ($message) use ($issueType) {
                    $message->to('hello@onwynd.com')
                        ->subject("ALERT: Session {$issueType}");
                }
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send session issue notification: '.$e->getMessage());
        }
    }
}
