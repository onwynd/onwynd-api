<?php

namespace App\Services\WebSocket;

use App\Models\Chat;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Ratchet\ConnectionInterface;
use SplObjectStorage;

/**
 * WebSocket Service
 *
 * Manages real-time connections and message routing for chat functionality.
 * Supports:
 * - User registration and connection management
 * - Direct messaging between users
 * - Group/channel subscriptions
 * - Connection pooling and resource cleanup
 */
class WebSocketService
{
    /**
     * Active client connections storage.
     */
    protected SplObjectStorage $clients;

    /**
     * Map of user IDs to their connection resource IDs.
     * Format: [user_id => [resource_id1, resource_id2, ...]]
     */
    protected array $userConnections;

    /**
     * Map of resource IDs to user IDs.
     * Format: [resource_id => user_id]
     */
    protected array $resourceToUser;

    /**
     * Channel subscriptions.
     * Format: [resource_id => channel_name]
     */
    protected array $subscriptions;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->clients = new SplObjectStorage;
        $this->userConnections = [];
        $this->resourceToUser = [];
        $this->subscriptions = [];
    }

    /**
     * Handle new connection.
     */
    public function handleOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        Log::info('WebSocket connection opened', ['resource_id' => $conn->resourceId]);
    }

    /**
     * Handle incoming message.
     */
    public function handleMessage(ConnectionInterface $conn, string $msg): void
    {
        try {
            $data = json_decode($msg, true);

            if (! isset($data['command'])) {
                $conn->send(json_encode(['error' => 'Invalid command']));

                return;
            }

            switch ($data['command']) {
                case 'register':
                    $this->registerUser($conn, $data);
                    break;
                case 'message':
                    $this->sendDirectMessage($conn, $data);
                    break;
                case 'subscribe':
                    $this->subscribeToChannel($conn, $data);
                    break;
                case 'broadcast':
                    $this->broadcastToChannel($conn, $data);
                    break;
                case 'typing':
                    $this->broadcastTypingStatus($conn, $data);
                    break;
                case 'status':
                    $this->broadcastUserStatus($conn, $data);
                    break;
                default:
                    $conn->send(json_encode(['error' => 'Unknown command']));
            }
        } catch (Exception $e) {
            Log::error('WebSocket message error', [
                'error' => $e->getMessage(),
                'resource_id' => $conn->resourceId,
            ]);
            $conn->send(json_encode(['error' => 'Message processing failed']));
        }
    }

    /**
     * Register a user with a connection.
     */
    protected function registerUser(ConnectionInterface $conn, array $data): void
    {
        if (! isset($data['user_id'])) {
            $conn->send(json_encode(['error' => 'user_id required']));

            return;
        }

        $userId = $data['user_id'];

        // Store connection mapping
        if (! isset($this->userConnections[$userId])) {
            $this->userConnections[$userId] = [];
        }

        $this->userConnections[$userId][] = $conn->resourceId;
        $this->resourceToUser[$conn->resourceId] = $userId;

        // Notify user of successful registration
        $conn->send(json_encode([
            'success' => true,
            'message' => 'User registered successfully',
            'user_id' => $userId,
            'resource_id' => $conn->resourceId,
        ]));

        Log::info('User registered for WebSocket', ['user_id' => $userId, 'resource_id' => $conn->resourceId]);
    }

    /**
     * Send direct message between users.
     */
    protected function sendDirectMessage(ConnectionInterface $conn, array $data): void
    {
        if (! isset($data['to_user_id']) || ! isset($data['message'])) {
            $conn->send(json_encode(['error' => 'to_user_id and message required']));

            return;
        }

        $fromUserId = $this->resourceToUser[$conn->resourceId] ?? null;
        $toUserId = $data['to_user_id'];
        $message = $data['message'];

        if (! $fromUserId) {
            $conn->send(json_encode(['error' => 'User not registered']));

            return;
        }

        // Store message in database
        try {
            $chat = Chat::create([
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'message' => $message,
                'message_type' => $data['message_type'] ?? 'text',
                'attachments' => $data['attachments'] ?? null,
            ]);

            // Send to recipient if connected
            if (isset($this->userConnections[$toUserId])) {
                $messageData = [
                    'command' => 'message',
                    'chat_id' => $chat->id,
                    'from_user_id' => $fromUserId,
                    'message' => $message,
                    'timestamp' => $chat->created_at->toIso8601String(),
                ];

                foreach ($this->userConnections[$toUserId] as $resourceId) {
                    if (isset($this->clients[$resourceId]) || $resourceId < 999) {
                        // Send to all recipient's connections
                        foreach ($this->clients as $client) {
                            if ($client->resourceId == $resourceId) {
                                $client->send(json_encode($messageData));
                            }
                        }
                    }
                }
            }

            // Confirm to sender
            $conn->send(json_encode([
                'success' => true,
                'chat_id' => $chat->id,
                'message' => 'Message sent',
            ]));

        } catch (Exception $e) {
            Log::error('Failed to send direct message', ['error' => $e->getMessage()]);
            $conn->send(json_encode(['error' => 'Failed to send message']));
        }
    }

    /**
     * Subscribe user to a channel.
     */
    protected function subscribeToChannel(ConnectionInterface $conn, array $data): void
    {
        if (! isset($data['channel'])) {
            $conn->send(json_encode(['error' => 'channel required']));

            return;
        }

        $this->subscriptions[$conn->resourceId] = $data['channel'];

        $conn->send(json_encode([
            'success' => true,
            'message' => 'Subscribed to channel',
            'channel' => $data['channel'],
        ]));

        Log::info('User subscribed to channel', [
            'channel' => $data['channel'],
            'resource_id' => $conn->resourceId,
        ]);
    }

    /**
     * Broadcast message to channel subscribers.
     */
    protected function broadcastToChannel(ConnectionInterface $conn, array $data): void
    {
        if (! isset($data['channel']) || ! isset($data['message'])) {
            $conn->send(json_encode(['error' => 'channel and message required']));

            return;
        }

        $channel = $data['channel'];
        $message = [
            'command' => 'broadcast',
            'channel' => $channel,
            'message' => $data['message'],
            'from_user_id' => $this->resourceToUser[$conn->resourceId] ?? null,
            'timestamp' => now()->toIso8601String(),
        ];

        $messageJson = json_encode($message);

        // Send to all subscribers of this channel
        foreach ($this->subscriptions as $resourceId => $subChannel) {
            if ($subChannel === $channel) {
                foreach ($this->clients as $client) {
                    if ($client->resourceId == $resourceId) {
                        $client->send($messageJson);
                    }
                }
            }
        }
    }

    /**
     * Broadcast typing status.
     */
    protected function broadcastTypingStatus(ConnectionInterface $conn, array $data): void
    {
        if (! isset($data['to_user_id'])) {
            return;
        }

        $fromUserId = $this->resourceToUser[$conn->resourceId] ?? null;
        $toUserId = $data['to_user_id'];

        $typingData = [
            'command' => 'typing',
            'from_user_id' => $fromUserId,
            'is_typing' => $data['is_typing'] ?? true,
            'timestamp' => now()->toIso8601String(),
        ];

        // Send to recipient
        if (isset($this->userConnections[$toUserId])) {
            foreach ($this->userConnections[$toUserId] as $resourceId) {
                foreach ($this->clients as $client) {
                    if ($client->resourceId == $resourceId) {
                        $client->send(json_encode($typingData));
                    }
                }
            }
        }
    }

    /**
     * Broadcast user online/offline status.
     */
    protected function broadcastUserStatus(ConnectionInterface $conn, array $data): void
    {
        $userId = $this->resourceToUser[$conn->resourceId] ?? null;
        $status = $data['status'] ?? 'online'; // online, away, offline

        try {
            User::where('id', $userId)->update([
                'status' => $status,
                'last_seen_at' => now(),
            ]);

            // Broadcast to all clients
            $statusData = [
                'command' => 'user_status',
                'user_id' => $userId,
                'status' => $status,
                'timestamp' => now()->toIso8601String(),
            ];

            foreach ($this->clients as $client) {
                $client->send(json_encode($statusData));
            }
        } catch (Exception $e) {
            Log::error('Failed to broadcast user status', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle connection close.
     */
    public function handleClose(ConnectionInterface $conn): void
    {
        // Get the user ID for this connection
        $userId = $this->resourceToUser[$conn->resourceId] ?? null;

        // Clean up subscriptions
        if (isset($this->subscriptions[$conn->resourceId])) {
            unset($this->subscriptions[$conn->resourceId]);
        }

        // Clean up resource to user mapping
        unset($this->resourceToUser[$conn->resourceId]);

        // Clean up user connections
        if ($userId && isset($this->userConnections[$userId])) {
            $this->userConnections[$userId] = array_filter(
                $this->userConnections[$userId],
                fn ($rid) => $rid !== $conn->resourceId
            );

            // Remove user if no more connections
            if (empty($this->userConnections[$userId])) {
                unset($this->userConnections[$userId]);

                // Update user status to offline
                try {
                    User::where('id', $userId)->update(['status' => 'offline']);

                    // Notify others of offline status
                    $statusData = [
                        'command' => 'user_status',
                        'user_id' => $userId,
                        'status' => 'offline',
                        'timestamp' => now()->toIso8601String(),
                    ];

                    foreach ($this->clients as $client) {
                        $client->send(json_encode($statusData));
                    }
                } catch (Exception $e) {
                    Log::error('Failed to update user offline status', ['error' => $e->getMessage()]);
                }
            }
        }

        // Detach client
        $this->clients->detach($conn);

        Log::info('WebSocket connection closed', ['resource_id' => $conn->resourceId, 'user_id' => $userId]);
    }

    /**
     * Handle connection error.
     */
    public function handleError(ConnectionInterface $conn, Exception $e): void
    {
        Log::error('WebSocket error', [
            'resource_id' => $conn->resourceId,
            'error' => $e->getMessage(),
            'user_id' => $this->resourceToUser[$conn->resourceId] ?? null,
        ]);

        $conn->close();
    }

    /**
     * Get active users count.
     */
    public function getActiveUsersCount(): int
    {
        return count($this->userConnections);
    }

    /**
     * Get total connections count.
     */
    public function getTotalConnectionsCount(): int
    {
        return $this->clients->count();
    }

    /**
     * Check if user is online.
     */
    public function isUserOnline(int $userId): bool
    {
        return isset($this->userConnections[$userId]) && ! empty($this->userConnections[$userId]);
    }
}
