<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TypingIndicator implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;

    public $channelName;

    public function __construct($userId, $channelName)
    {
        $this->userId = $userId;
        $this->channelName = $channelName;
    }

    public function broadcastOn(): array
    {
        if (str_starts_with($this->channelName, 'private-')) {
            return [new PrivateChannel(substr($this->channelName, 8))];
        }
        if (str_starts_with($this->channelName, 'presence-')) {
            return [new PresenceChannel(substr($this->channelName, 9))];
        }

        return [new Channel($this->channelName)];
    }

    public function broadcastAs(): string
    {
        return 'typing';
    }
}
