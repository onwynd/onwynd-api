<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DemoRequestConfirmationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $contactName,
        public string $companyName,
        public string $orgType,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Onwynd demo request — we\'ll be in touch 🌿',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.demo.confirmation',
            with: [
                'contactName'  => $this->contactName,
                'companyName'  => $this->companyName,
                'orgType'      => $this->orgType,
                'previewText'  => 'Demo Request Confirmation',
            ],
        );
    }
}
