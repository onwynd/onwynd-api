<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminSessionBookingNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ?User $patient,
        public readonly string $therapistName,
        public readonly string $dateTime,
        public readonly float $amount,
        public readonly string $currency = 'NGN',
    ) {}

    public function envelope(): Envelope
    {
        $patientName = $this->patient
            ? ($this->patient->first_name.' '.($this->patient->last_name ?? ''))
            : 'A user';

        return new Envelope(
            subject: "[Onwynd] New session booked — {$patientName} with {$this->therapistName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.session-booking-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
