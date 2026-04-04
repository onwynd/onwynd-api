<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DemoRequestAlertEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Lead   $lead,
        public string $recipientName,
        public string $orgType,
        public string $companySize,
        public string $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔴 New demo request: ' . $this->lead->company . ' (' . $this->orgType . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.demo.alert',
            with: [
                'lead'          => $this->lead,
                'recipientName' => $this->recipientName,
                'orgType'       => $this->orgType,
                'companySize'   => $this->companySize,
                'message'       => $this->message,
                'previewText'   => 'New demo request requires follow-up',
            ],
        );
    }
}
