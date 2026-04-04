<?php

namespace App\Listeners;

use App\Models\MailLog;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\Events\JobFailed;

class LogMailFailed
{
    public function handle(JobFailed $event): void
    {
        try {
            // Only handle failed mail jobs
            $payload = $event->job->payload();
            $command = isset($payload['data']['command'])
                ? unserialize($payload['data']['command'])
                : null;

            // Check if it's a queued mailable (SendQueuedMailable)
            if (! ($command instanceof \Illuminate\Mail\SendQueuedMailable)) {
                return;
            }

            $mailable = $command->mailable ?? null;
            $mailableClass = $mailable ? get_class($mailable) : null;

            // Extract recipient from the mailable's "to" property
            $recipient = '—';
            if ($mailable && property_exists($mailable, 'to') && is_array($mailable->to)) {
                $recipient = $mailable->to[0]['address'] ?? '—';
            }

            // Try to get subject
            $subject = null;
            if ($mailable && method_exists($mailable, 'envelope')) {
                try {
                    $envelope = $mailable->envelope();
                    $subject  = $envelope->subject ?? null;
                } catch (\Throwable) {
                }
            }

            MailLog::create([
                'mailable_class' => $mailableClass,
                'recipient'      => $recipient,
                'subject'        => $subject,
                'status'         => 'failed',
                'failure_reason' => $event->exception->getMessage(),
                'metadata'       => [
                    'exception_class' => get_class($event->exception),
                    'queue'           => $event->job->getQueue(),
                ],
                'failed_at' => now(),
            ]);
        } catch (\Throwable) {
            // Never let logging break queue processing
        }
    }
}
