<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayrollNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $employeeName;

    public $month;

    public $netAmount;

    public $link; // Link to download payslip

    public function __construct($employeeName, $month, $netAmount, $link)
    {
        $this->employeeName = $employeeName;
        $this->month = $month;
        $this->netAmount = $netAmount;
        $this->link = $link;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payslip Available for '.$this->month.' - '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hr.payroll',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
