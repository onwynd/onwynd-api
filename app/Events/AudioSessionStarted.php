<?php

namespace App\Events;

use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AudioSessionStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $session;

    public $initiator;

    public function __construct(TherapySession $session, User $initiator)
    {
        $this->session = $session;
        $this->initiator = $initiator;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('therapy-session.'.$this->session->id);
    }
}
