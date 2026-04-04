<?php

namespace App\Mail;

use App\Models\ContactSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactFormSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public ContactSubmission $submission;

    public array $payload;

    public string $ticketId;

    public function __construct(ContactSubmission $submission, array $payload, string $ticketId)
    {
        $this->submission = $submission;
        $this->payload    = $payload;
        $this->ticketId   = $ticketId;
        $this->subject(sprintf('[Onwynd] New contact: %s (%s)', $payload['name'] ?? 'n/a', $payload['subject'] ?? 'general'));
    }

    public function build()
    {
        return $this->view('emails.contact.form-submitted')
            ->with([
                'submission' => $this->submission,
                // Keep 'lead' alias so existing blade template doesn't break
                'lead'       => $this->submission,
                'payload'    => $this->payload,
                'ticketId'   => $this->ticketId,
            ]);
    }
}
