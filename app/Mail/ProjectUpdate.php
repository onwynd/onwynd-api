<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProjectUpdate extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;

    public $projectName;

    public $updateMessage;

    public $updaterName;

    public $timestamp;

    public $nextSteps;

    public $projectLink;

    public function __construct($name, $projectName, $updateMessage, $updaterName, $timestamp, $nextSteps, $projectLink)
    {
        $this->name = $name;
        $this->projectName = $projectName;
        $this->updateMessage = $updateMessage;
        $this->updaterName = $updaterName;
        $this->timestamp = $timestamp;
        $this->nextSteps = $nextSteps;
        $this->projectLink = $projectLink;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Project Update: '.$this->projectName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.workplace.project-update',
        );
    }
}
