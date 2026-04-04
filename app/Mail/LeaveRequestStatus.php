<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeaveRequestStatus extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $employeeName;

    public $status; // approved, rejected

    public $leaveType;

    public $dates;

    public $managerName;

    public $reason; // Rejection reason if applicable

    public function __construct($employeeName, $status, $leaveType, $dates, $managerName, $reason = null)
    {
        $this->employeeName = $employeeName;
        $this->status = $status;
        $this->leaveType = $leaveType;
        $this->dates = $dates;
        $this->managerName = $managerName;
        $this->reason = $reason;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Leave Request '.ucfirst($this->status).' - '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hr.leave-status',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
