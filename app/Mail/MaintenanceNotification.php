<?php

namespace App\Mail;

use App\Models\MaintenanceSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MaintenanceNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $schedule;

    /**
     * Create a new message instance.
     */
    public function __construct(MaintenanceSchedule $schedule)
    {
        $this->schedule = $schedule;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Scheduled Maintenance: '.$this->schedule->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.maintenance.notification',
            with: [
                'title' => $this->schedule->title,
                'description' => $this->schedule->description,
                'start_time' => $this->schedule->start_time->format('F j, Y g:i A'),
                'end_time' => $this->schedule->end_time->format('F j, Y g:i A'),
                'services' => $this->schedule->affected_services ? implode(', ', $this->schedule->affected_services) : 'All Services',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
