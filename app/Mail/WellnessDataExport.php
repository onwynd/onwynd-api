<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WellnessDataExport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $userEmail,
        public readonly string $fromDate,
        public readonly string $toDate,
        public readonly array $exportData,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Onwynd Wellness Data Export',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wellness.data-export',
        );
    }

    public function attachments(): array
    {
        $json = json_encode($this->exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return [
            Attachment::fromData(fn () => $json, 'onwynd-wellness-export.json')
                ->withMime('application/json'),
        ];
    }
}
