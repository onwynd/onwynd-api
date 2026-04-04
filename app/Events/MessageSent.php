<?php

namespace App\Events;

use App\Models\Therapy\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(ChatMessage $message)
    {
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        $channels = [];

        if ($this->message->chatable_id) {
            // Session or Group Channel
            // Format: chat.{type}.{id}
            // e.g. chat.session.uuid, chat.group.uuid
            $type = strtolower(class_basename($this->message->chatable_type));
            $channels[] = new PrivateChannel("chat.{$type}.{$this->message->chatable_id}");
        } elseif ($this->message->receiver_id) {
            // Direct Message Channel (User specific)
            $channels[] = new PrivateChannel("user.{$this->message->receiver_id}");
            // Also broadcast to sender so they see it across devices
            $channels[] = new PrivateChannel("user.{$this->message->sender_id}");
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'message' => $this->message->message,
            'sender' => [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->first_name.' '.$this->message->sender->last_name,
                'avatar' => $this->message->sender->profile_photo,
            ],
            'type' => $this->message->type,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
