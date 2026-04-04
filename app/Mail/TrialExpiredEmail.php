<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TrialExpiredEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $billingUrl;

    public $extensionUrl;

    public function __construct($billingUrl, $extensionUrl = null)
    {
        $this->billingUrl = $billingUrl;
        $this->extensionUrl = $extensionUrl;
    }

    public function build()
    {
        return $this->subject('Your Free Trial Has Expired')
            ->view('emails.subscription.trial-expired');
    }
}
