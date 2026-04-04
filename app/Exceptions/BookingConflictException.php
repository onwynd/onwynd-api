<?php

namespace App\Exceptions;

use Exception;

class BookingConflictException extends Exception
{
    public string $nextAvailableSlot;

    public function __construct(string $message, string $nextAvailableSlot)
    {
        parent::__construct($message);
        $this->nextAvailableSlot = $nextAvailableSlot;
    }
}
