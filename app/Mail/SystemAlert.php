<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SystemAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $adminName;

    public $alertLevel; // info, warning, critical

    public $messageBody;

    public $timestamp;

    public function __construct($adminName, $alertLevel, $messageBody, $timestamp)
    {
        $this->adminName = $adminName;
        $this->alertLevel = $alertLevel;
        $this->messageBody = $messageBody;
        $this->timestamp = $timestamp;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'System Alert: '.strtoupper($this->alertLevel),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.system-alert',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
