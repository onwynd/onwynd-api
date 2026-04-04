<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * User Online Event
 *
 * Fired when a user comes online.
 * Used to broadcast user online status to other users.
 */
class UserOnline
{
    use Dispatchable, SerializesModels;

    /**
     * The user who came online.
     */
    public User $user;

    /**
     * Constructor.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return ['public-presence'];
    }

    /**
     * Get the data that should be broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event' => 'user_online',
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'avatar' => $this->user->avatar ?? null,
            'status' => 'online',
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
