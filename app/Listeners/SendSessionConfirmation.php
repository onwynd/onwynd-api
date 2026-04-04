<?php

namespace App\Listeners;

use App\Events\SessionBooked;

class SendSessionConfirmation
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(SessionBooked $event): void
    {
        //
    }
}
