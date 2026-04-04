<?php

namespace App\Listeners;

use App\Models\MailLog;
use Illuminate\Mail\Events\MessageSent;

class LogMailSent
{
    public function handle(MessageSent $event): void
    {
        try {
            $message = $event->message;

            $to = collect($message->getTo())
                ->keys()
                ->first() ?? '—';

            $subject = $message->getSubject() ?? null;

            // Try to resolve the mailable class from the event
            $mailableClass = null;
            if (property_exists($event, 'data') && isset($event->data['mailable'])) {
                $mailableClass = get_class($event->data['mailable']);
            }

            MailLog::create([
                'mailable_class' => $mailableClass,
                'recipient'      => $to,
                'subject'        => $subject,
                'status'         => 'sent',
                'sent_at'        => now(),
            ]);
        } catch (\Throwable) {
            // Never let logging break mail delivery
        }
    }
}
