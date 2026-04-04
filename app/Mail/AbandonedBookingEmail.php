<?php

namespace App\Mail;

use App\Models\BookingIntent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbandonedBookingEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public BookingIntent $intent) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your session is still waiting for you 💚',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking.abandoned',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
