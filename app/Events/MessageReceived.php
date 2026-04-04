<?php

namespace App\Events;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Message Received Event
 *
 * Fired when a new chat message is received.
 * Used for real-time notifications and broadcasting.
 */
class MessageReceived
{
    use Dispatchable, SerializesModels;

    /**
     * The chat message instance.
     */
    public Chat $chat;

    /**
     * The sender user instance.
     */
    public User $sender;

    /**
     * The recipient user instance.
     */
    public User $recipient;

    /**
     * Constructor.
     */
    public function __construct(Chat $chat, User $sender, User $recipient)
    {
        $this->chat = $chat;
        $this->sender = $sender;
        $this->recipient = $recipient;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            'private-user.'.$this->recipient->id,
            'private-chat.'.md5($this->sender->id.$this->recipient->id),
        ];
    }

    /**
     * Get the data that should be broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'chat_id' => $this->chat->id,
            'message' => $this->chat->message,
            'from_user_id' => $this->sender->id,
            'from_user_name' => $this->sender->name,
            'to_user_id' => $this->recipient->id,
            'message_type' => $this->chat->message_type,
            'created_at' => $this->chat->created_at->toIso8601String(),
        ];
    }
}
