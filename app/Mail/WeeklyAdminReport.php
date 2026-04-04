<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyAdminReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $startDate;

    public $endDate;

    public $metrics;

    public $aiAnalysis;

    public $forecast;

    public $actionSteps;

    public function __construct($startDate, $endDate, $metrics, $aiAnalysis, $forecast, $actionSteps)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->metrics = $metrics;
        $this->aiAnalysis = $aiAnalysis;
        $this->forecast = $forecast;
        $this->actionSteps = $actionSteps;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Weekly Performance Report & AI Insights',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reports.weekly-admin-performance',
        );
    }
}
