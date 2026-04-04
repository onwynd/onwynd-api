<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $changedAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Password Was Changed — Action Required If This Wasn\'t You',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.security.password-changed',
        );
    }
}
