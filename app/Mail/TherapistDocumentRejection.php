<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TherapistDocumentRejection extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $therapistName;

    public $reason;

    public $link;

    /**
     * Create a new message instance.
     */
    public function __construct($therapistName, $reason, $link = null)
    {
        $this->therapistName = $therapistName;
        $this->reason = $reason;
        $this->link = $link ?? url('/therapist/profile/documents'); // Direct link to documents upload if possible
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action Required: Document Verification Issue - '.config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.therapist.document-rejection',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
