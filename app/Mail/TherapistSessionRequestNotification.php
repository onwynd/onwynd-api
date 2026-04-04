<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TherapistSessionRequestNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $patientName;

    public $therapistName;

    public $dateTime;

    public $sessionLink;

    /**
     * Create a new message instance.
     */
    public function __construct($patientName, $therapistName, $dateTime, $sessionLink)
    {
        $this->patientName = $patientName;
        $this->therapistName = $therapistName;
        $this->dateTime = $dateTime;
        $this->sessionLink = $sessionLink;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Session Request - '.config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment.therapist-session-request',
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
