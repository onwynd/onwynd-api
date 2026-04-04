<?php

namespace App\Events\Support;

use App\Models\SupportChat;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an agent takes over the chat from the AI bot.
 * The customer's UI transitions silently (no "AI → Human" banner shown).
 * The agent panel in the dashboard shows an "Active" indicator.
 */
class SupportChatHandedOver implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SupportChat $chat) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support.chat.'.$this->chat->uuid),
            new PrivateChannel('support.agents'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.handover';
    }

    public function broadcastWith(): array
    {
        $agent = $this->chat->assignedAgent;

        return [
            'chat_uuid' => $this->chat->uuid,
            'status' => $this->chat->status,
            'agent_id' => $agent?->id,
            'agent_name' => $agent ? trim($agent->first_name.' '.$agent->last_name) : null,
            'agent_avatar' => $agent?->profile_photo,
            'handover_at' => $this->chat->handover_at?->toIso8601String(),
        ];
    }
}
