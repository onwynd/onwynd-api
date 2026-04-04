<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * User Offline Event
 *
 * Fired when a user goes offline.
 * Used to broadcast user offline status to other users.
 */
class UserOffline
{
    use Dispatchable, SerializesModels;

    /**
     * The user who went offline.
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
            'event' => 'user_offline',
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'status' => 'offline',
            'last_seen_at' => $this->user->last_seen_at?->toIso8601String(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
