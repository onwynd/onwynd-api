<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;

    public $items;

    public $tax;

    public $total;

    public $statusUrl;

    public function __construct($name, $items, $tax, $total, $statusUrl = null)
    {
        $this->name = $name;
        $this->items = $items;
        $this->tax = $tax;
        $this->total = $total;
        $this->statusUrl = $statusUrl;
    }

    public function build()
    {
        return $this->subject('Your Invoice')
            ->view('emails.billing.invoice');
    }
}
