<?php

namespace App\Events\Support;

use App\Models\SupportChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast immediately (no queue) so the chat feels real-time.
 *
 * Channels:
 *   private-support.chat.{chatUuid}   → customer + the assigned agent
 *   private-support.agents            → all support agents (to show unread badge)
 */
class SupportChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SupportChatMessage $chatMessage) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('support.chat.'.$this->chatMessage->chat->uuid),
        ];

        // Also push to the agents' global channel so their sidebar can update
        $channels[] = new PrivateChannel('support.agents');

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'support.message';
    }

    public function broadcastWith(): array
    {
        $msg = $this->chatMessage;
        $sender = null;

        if ($msg->sender_id && in_array($msg->sender_type, ['user', 'agent'])) {
            $sender = [
                'id' => $msg->sender->id ?? null,
                'name' => trim(($msg->sender->first_name ?? '').' '.($msg->sender->last_name ?? '')),
                'avatar' => $msg->sender->profile_photo ?? null,
            ];
        }

        return [
            'id' => $msg->id,
            'chat_uuid' => $msg->chat->uuid,
            'sender_type' => $msg->sender_type,
            'sender' => $sender,
            'message' => $msg->message,
            'metadata' => $msg->metadata,
            'created_at' => $msg->created_at->toIso8601String(),
        ];
    }
}
