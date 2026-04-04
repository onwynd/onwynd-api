<?php

namespace App\Events;

use App\Models\TherapySession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AudioSessionEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $session;

    public function __construct(TherapySession $session)
    {
        $this->session = $session;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('therapy-session.'.$this->session->id);
    }
}
