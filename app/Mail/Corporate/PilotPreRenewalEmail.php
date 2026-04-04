<?php

namespace App\Mail\Corporate;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PilotPreRenewalEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $orgName,
        public readonly string $hrName,
        public readonly Carbon $pilotEnd,
        public readonly int    $sessionsUsed,
        public readonly int    $sessionsTotal,
        public readonly string $renewalUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Onwynd Pilot Ends in 14 Days — Here's What to Do",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.corporate.pilot-pre-renewal',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
