<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SessionCheckIn extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;

    public $therapistName;

    public $dashboardLink;

    public function __construct($name, $therapistName, $dashboardLink)
    {
        $this->name = $name;
        $this->therapistName = $therapistName;
        $this->dashboardLink = $dashboardLink;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Checking in: How are you feeling?',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.therapy.session-checkin',
        );
    }
}
