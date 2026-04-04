<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AmbassadorWelcome extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;

    public $referralCode;

    public $dashboardLink;

    public function __construct($name, $referralCode, $dashboardLink)
    {
        $this->name = $name;
        $this->referralCode = $referralCode;
        $this->dashboardLink = $dashboardLink;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to the Ambassador Program! 🌟',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.engagement.ambassador-welcome',
        );
    }
}
