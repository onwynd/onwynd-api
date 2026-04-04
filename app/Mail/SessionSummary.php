<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SessionSummary extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $patientName;

    public $therapistName;

    public $sessionDate;

    public $summary;

    public $recommendations;

    public $homework;

    public $dashboardLink;

    public function __construct($patientName, $therapistName, $sessionDate, $summary, $recommendations, $homework, $dashboardLink)
    {
        $this->patientName = $patientName;
        $this->therapistName = $therapistName;
        $this->sessionDate = $sessionDate;
        $this->summary = $summary;
        $this->recommendations = $recommendations;
        $this->homework = $homework;
        $this->dashboardLink = $dashboardLink;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Session Summary & Insights',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.therapy.session-summary',
        );
    }
}
