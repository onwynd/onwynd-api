<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Welcome email for employees of an institutional partner (corporate, university, NGO).
 * Branded with the partner institution's logo, falls back to Onwynd branding.
 * NOT for Onwynd's own internal staff — use EmployeeWelcomeEmail for those.
 */
class InstitutionalEmployeeWelcomeEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public readonly string $logoUrl;

    public function __construct(
        public readonly string  $name,
        public readonly string  $role,
        public readonly string  $institutionName,
        public readonly string  $loginUrl,
        public readonly ?string $institutionLogo = null,
    ) {
        // Resolve: institution logo → Onwynd DB branding logo → Onwynd default asset
        $this->logoUrl = $institutionLogo
            ?? Setting::getValue('logo_url')
            ?? (config('app.url') . '/img/logo.png');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Welcome to {$this->institutionName} — Your Wellness Account is Ready",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.institutional.employee-welcome',
        );
    }
}
