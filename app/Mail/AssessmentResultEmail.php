<?php

namespace App\Mail;

use App\Models\Assessment;
use App\Models\UserAssessmentResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssessmentResultEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public UserAssessmentResult $result,
        public Assessment $assessment,
    ) {}

    public function envelope(): Envelope
    {
        $title = $this->assessment->title ?? 'Assessment';

        return new Envelope(
            subject: "Your {$title} results are ready",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.assessment.result',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
