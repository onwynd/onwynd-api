<?php

namespace App\Mail\Corporate;

use App\Helpers\EmailAmountFormatter;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PilotActivatedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $amounts;

    public function __construct(
        public readonly string $orgName,
        public readonly string $hrName,
        public readonly Carbon $pilotStart,
        public readonly Carbon $pilotEnd,
        public readonly int    $sessionQuota,
        public readonly string $currency,
        float                  $sessionFee = 0.0,
        float                  $bookingFee = 0.0,
    ) {
        $this->amounts = EmailAmountFormatter::formatTotal($sessionFee, $bookingFee, 0.0, $currency);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Onwynd Pilot is Now Live — {$this->orgName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.corporate.pilot-activated',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
