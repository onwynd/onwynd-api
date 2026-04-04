<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;

    public $loginUrl;

    public function __construct($name, $loginUrl)
    {
        $this->name = $name;
        $this->loginUrl = $loginUrl;
    }

    public function build()
    {
        return $this->subject('Welcome to '.config('app.name'))
            ->view('emails.auth.welcome');
    }
}
