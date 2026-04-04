<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Welcome email for Onwynd's own internal employees (HR, admin, support, etc.).
 * NOT for institutional/partner employees — use InstitutionalEmployeeWelcomeEmail for those.
 */
class EmployeeWelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $role,
        public readonly string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to ' . config('app.name', 'Onwynd') . ' — Your Account is Ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.employee.welcome',
        );
    }
}
