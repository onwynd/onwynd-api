<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentShared extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;

    public $sharerName;

    public $documentName;

    public $fileType;

    public $fileSize;

    public $message;

    public $link;

    public function __construct($name, $sharerName, $documentName, $fileType, $fileSize, $message, $link)
    {
        $this->name = $name;
        $this->sharerName = $sharerName;
        $this->documentName = $documentName;
        $this->fileType = $fileType;
        $this->fileSize = $fileSize;
        $this->message = $message;
        $this->link = $link;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->sharerName.' shared a document with you',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.workplace.document-shared',
        );
    }
}
