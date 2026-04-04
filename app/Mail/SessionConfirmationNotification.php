<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SessionConfirmationNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $patientName;

    public $therapistName;

    public $sessionDateTime;

    /**
     * Create a new message instance.
     */
    public function __construct(string $patientName, string $therapistName, string $sessionDateTime)
    {
        $this->patientName = $patientName;
        $this->therapistName = $therapistName;
        $this->sessionDateTime = $sessionDateTime;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your therapy session has been confirmed',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.session-confirmed',
            with: [
                'patientName' => $this->patientName,
                'therapistName' => $this->therapistName,
                'sessionDateTime' => $this->sessionDateTime,
            ],
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
