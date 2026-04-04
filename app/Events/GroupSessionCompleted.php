<?php

namespace App\Events;

use App\Models\GroupSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupSessionCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $session;

    /**
     * Create a new event instance.
     */
    public function __construct(GroupSession $session)
    {
        $this->session = $session;
    }
}
