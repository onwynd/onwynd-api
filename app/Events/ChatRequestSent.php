<?php

namespace App\Events;

use App\Models\ChatRequest;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Chat Request Sent Event
 *
 * Fired when a user sends a chat request to another user.
 * Used for real-time notifications.
 */
class ChatRequestSent
{
    use Dispatchable, SerializesModels;

    /**
     * The chat request instance.
     */
    public ChatRequest $chatRequest;

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
    public function __construct(ChatRequest $chatRequest, User $sender, User $recipient)
    {
        $this->chatRequest = $chatRequest;
        $this->sender = $sender;
        $this->recipient = $recipient;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return ['private-user.'.$this->recipient->id];
    }

    /**
     * Get the data that should be broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'chat_request_id' => $this->chatRequest->id,
            'message' => $this->chatRequest->message,
            'from_user_id' => $this->sender->id,
            'from_user_name' => $this->sender->name,
            'from_user_avatar' => $this->sender->avatar ?? null,
            'to_user_id' => $this->recipient->id,
            'status' => $this->chatRequest->status,
            'created_at' => $this->chatRequest->created_at->toIso8601String(),
        ];
    }
}
