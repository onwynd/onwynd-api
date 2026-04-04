<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskAssigned extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $assigneeName;

    public $taskTitle;

    public $projectName;

    public $dueDate;

    public $assignerName;

    public $link;

    public function __construct($assigneeName, $taskTitle, $projectName, $dueDate, $assignerName, $link)
    {
        $this->assigneeName = $assigneeName;
        $this->taskTitle = $taskTitle;
        $this->projectName = $projectName;
        $this->dueDate = $dueDate;
        $this->assignerName = $assignerName;
        $this->link = $link;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Task Assigned: '.$this->taskTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.project.task-assigned',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
