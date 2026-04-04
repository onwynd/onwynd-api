<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCancelledEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $feedbackUrl;

    public function __construct($feedbackUrl = null)
    {
        $this->feedbackUrl = $feedbackUrl;
    }

    public function build()
    {
        return $this->subject('Subscription Cancelled')
            ->view('emails.subscription.cancelled');
    }
}
