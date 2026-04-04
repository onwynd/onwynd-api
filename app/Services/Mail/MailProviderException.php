<?php

namespace App\Services\Mail;

class MailProviderException extends \Exception
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly string $suggestion = ''
    ) {
        parent::__construct($message);
    }
}
