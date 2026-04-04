<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketStatusUpdate extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $userName;

    public $ticketId;

    public $status;

    public $link;

    public function __construct($userName, $ticketId, $status, $link)
    {
        $this->userName = $userName;
        $this->ticketId = $ticketId;
        $this->status = $status;
        $this->link = $link;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ticket #'.$this->ticketId.' Updated - '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.support.ticket-update',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
