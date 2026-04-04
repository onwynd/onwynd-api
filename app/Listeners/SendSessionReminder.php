<?php

namespace App\Listeners;

use App\Events\SessionBooked;

class SendSessionReminder
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
