<?php

namespace App\Mail\Corporate;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PilotExpiredEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $orgName,
        public readonly string $hrName,
        public readonly Carbon $expiryDate,
        public readonly int    $sessionsUsed,
        public readonly int    $sessionsTotal,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Onwynd Pilot Has Ended',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.corporate.pilot-expired',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
