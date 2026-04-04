<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentPendingConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $patientName;

    public $therapistName;

    public $dateTime;

    public $link;

    /**
     * Create a new message instance.
     */
    public function __construct($patientName, $therapistName, $dateTime, $link)
    {
        $this->patientName = $patientName;
        $this->therapistName = $therapistName;
        $this->dateTime = $dateTime;
        $this->link = $link;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Appointment Pending Confirmation - '.config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment.pending-confirmation',
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
