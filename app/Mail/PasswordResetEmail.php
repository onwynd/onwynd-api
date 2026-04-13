<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $url;
    public string $name;

    public function __construct(string $url, string $name = '')
    {
        $this->url  = $url;
        $this->name = $name;
    }

    public function build(): static
    {
        return $this->subject('Reset your Onwynd password')
            ->view('emails.auth.reset-password');
    }
}
