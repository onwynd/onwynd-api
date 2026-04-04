<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $messageIds;

    public $readByUserId;

    public $channelName;

    public function __construct(array $messageIds, $readByUserId, $channelName)
    {
        $this->messageIds = $messageIds;
        $this->readByUserId = $readByUserId;
        $this->channelName = $channelName;
    }

    public function broadcastOn(): array
    {
        if (str_starts_with($this->channelName, 'private-')) {
            return [new PrivateChannel(substr($this->channelName, 8))];
        }

        return [new Channel($this->channelName)];
    }

    public function broadcastWith(): array
    {
        return [
            'ids' => $this->messageIds,
            'user_id' => $this->readByUserId,
            'read_at' => now()->toIso8601String(),
        ];
    }
}
