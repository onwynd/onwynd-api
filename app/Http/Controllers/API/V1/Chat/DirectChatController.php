<?php

namespace App\Http\Controllers\API\V1\Chat;

use App\Events\ChatRequestSent;
use App\Events\MessageReceived;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\CreateChatRequestRequest;
use App\Http\Requests\Chat\MarkAsReadRequest;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Models\Chat;
use App\Models\ChatRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Direct Chat Controller
 *
 * Handles direct user-to-user messaging functionality.
 * Includes chat messages, chat requests, and conversation management.
 */
class DirectChatController extends Controller
{
    /**
     * Send a direct message to another user.
     *
     * POST /api/v1/chat/messages
     */
    public function sendMessage(SendMessageRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $toUserId = $request->validated()['to_user_id'];
            $message = $request->validated()['message'];
            $messageType = $request->validated()['message_type'] ?? 'text';
            $attachments = $request->validated()['attachments'] ?? null;

            // Check if both users have accepted chat requests or no restriction
            $chatRequest = ChatRequest::where(function ($query) use ($user, $toUserId) {
                $query->where('from_user_id', $user->id)
                    ->where('to_user_id', $toUserId);
            })->orWhere(function ($query) use ($user, $toUserId) {
                $query->where('from_user_id', $toUserId)
                    ->where('to_user_id', $user->id);
            })->first();

            // Create the message
            $chat = Chat::create([
                'from_user_id' => $user->id,
                'to_user_id' => $toUserId,
                'message' => $message,
                'message_type' => $messageType,
                'attachments' => $attachments ? json_encode($attachments) : null,
            ]);

            // Load relationships
            $chat->load('sender', 'recipient');

            // Broadcast message event
            broadcast(new MessageReceived($chat, $user, $chat->recipient))->toOthers();

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => $chat,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Failed to send message', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get conversation between two users.
     *
     * GET /api/v1/chat/conversations/{userId}
     */
    public function getConversation(int $userId, Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $perPage = $request->input('per_page', 20);
            $page = $request->input('page', 1);

            // Get conversation between authenticated user and specified user
            $messages = Chat::conversation($authUser->id, $userId)
                ->with('sender', 'recipient')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            // Mark messages as read for authenticated user
            Chat::where('to_user_id', $authUser->id)
                ->where('from_user_id', $userId)
                ->whereNull('read_at')
                ->update(['is_read' => true, 'read_at' => now()]);

            return response()->json([
                'success' => true,
                'data' => [
                    'messages' => $messages->items(),
                    'pagination' => [
                        'total' => $messages->total(),
                        'per_page' => $messages->perPage(),
                        'current_page' => $messages->currentPage(),
                        'last_page' => $messages->lastPage(),
                        'has_more' => $messages->hasMorePages(),
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch conversation', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch conversation: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all conversations (paginated list of users with recent messages).
     *
     * GET /api/v1/chat/conversations
     */
    public function getConversations(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $perPage = $request->input('per_page', 20);
            $sortBy = $request->input('sort_by', 'latest'); // latest, oldest, unread

            // Get distinct users this user has chatted with
            $userIds = Chat::where('from_user_id', $authUser->id)
                ->orWhere('to_user_id', $authUser->id)
                ->distinct()
                ->pluck(DB::raw('CASE WHEN from_user_id = '.$authUser->id.' THEN to_user_id ELSE from_user_id END as user_id'))
                ->unique();

            $conversations = User::whereIn('id', $userIds)
                ->with(['chats' => function ($query) use ($authUser) {
                    $query->where(function ($q) use ($authUser) {
                        $q->where('from_user_id', $authUser->id)
                            ->orWhere('to_user_id', $authUser->id);
                    })->orderBy('created_at', 'desc')
                        ->limit(1);
                }])
                ->when($sortBy === 'unread', function ($query) use ($authUser) {
                    return $query->withCount(['chats' => function ($q) use ($authUser) {
                        $q->where('to_user_id', $authUser->id)
                            ->where('is_read', false);
                    }])->orderByDesc('chats_count');
                })
                ->when($sortBy === 'latest', function ($query) {
                    return $query->orderBy('updated_at', 'desc');
                })
                ->when($sortBy === 'oldest', function ($query) {
                    return $query->orderBy('created_at', 'asc');
                })
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'conversations' => $conversations->items(),
                    'pagination' => [
                        'total' => $conversations->total(),
                        'per_page' => $conversations->perPage(),
                        'current_page' => $conversations->currentPage(),
                        'last_page' => $conversations->lastPage(),
                        'has_more' => $conversations->hasMorePages(),
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch conversations', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch conversations: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark messages as read.
     *
     * POST /api/v1/chat/mark-as-read
     */
    public function markAsRead(MarkAsReadRequest $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $chatIds = $request->validated()['chat_ids'];

            // Mark all messages as read
            $updated = Chat::whereIn('id', $chatIds)
                ->where('to_user_id', $authUser->id)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => "$updated messages marked as read",
                'data' => ['updated_count' => $updated],
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Failed to mark messages as read', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark messages as read: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a chat request to another user.
     *
     * POST /api/v1/chat/requests
     */
    public function sendChatRequest(CreateChatRequestRequest $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $validated = $request->validated();
            $toUserId = $validated['to_user_id'];
            $message = $validated['message'];

            // Check if request already exists
            $existingRequest = ChatRequest::where(function ($query) use ($authUser, $toUserId) {
                $query->where('from_user_id', $authUser->id)
                    ->where('to_user_id', $toUserId);
            })->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat request already sent to this user',
                ], 409);
            }

            // Create chat request
            $chatRequest = ChatRequest::create([
                'from_user_id' => $authUser->id,
                'to_user_id' => $toUserId,
                'message' => $message,
                'status' => ChatRequest::STATUS_PENDING,
            ]);

            $chatRequest->load('sender', 'recipient');

            // Broadcast event
            broadcast(new ChatRequestSent($chatRequest, $authUser, $chatRequest->recipient))->toOthers();

            return response()->json([
                'success' => true,
                'message' => 'Chat request sent successfully',
                'data' => $chatRequest,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Failed to send chat request', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send chat request: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get pending chat requests for the authenticated user.
     *
     * GET /api/v1/chat/requests/pending
     */
    public function getPendingRequests(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $perPage = $request->input('per_page', 20);

            $requests = ChatRequest::pending($authUser->id)
                ->with('sender')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'requests' => $requests->items(),
                    'pagination' => [
                        'total' => $requests->total(),
                        'per_page' => $requests->perPage(),
                        'current_page' => $requests->currentPage(),
                        'last_page' => $requests->lastPage(),
                        'has_more' => $requests->hasMorePages(),
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch pending requests', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending requests: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Accept a chat request.
     *
     * POST /api/v1/chat/requests/{requestId}/accept
     */
    public function acceptRequest(int $requestId): JsonResponse
    {
        try {
            $authUser = Auth::user();

            $chatRequest = ChatRequest::where('id', $requestId)
                ->where('to_user_id', $authUser->id)
                ->firstOrFail();

            $chatRequest->accept();
            $chatRequest->load('sender', 'recipient');

            return response()->json([
                'success' => true,
                'message' => 'Chat request accepted',
                'data' => $chatRequest,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Failed to accept chat request', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to accept chat request: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a chat request.
     *
     * POST /api/v1/chat/requests/{requestId}/reject
     */
    public function rejectRequest(int $requestId): JsonResponse
    {
        try {
            $authUser = Auth::user();

            $chatRequest = ChatRequest::where('id', $requestId)
                ->where('to_user_id', $authUser->id)
                ->firstOrFail();

            $chatRequest->reject();
            $chatRequest->load('sender', 'recipient');

            return response()->json([
                'success' => true,
                'message' => 'Chat request rejected',
                'data' => $chatRequest,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Failed to reject chat request', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject chat request: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Block a user from sending chat requests.
     *
     * POST /api/v1/chat/requests/{requestId}/block
     */
    public function blockUser(int $requestId): JsonResponse
    {
        try {
            $authUser = Auth::user();

            $chatRequest = ChatRequest::where('id', $requestId)
                ->where('to_user_id', $authUser->id)
                ->firstOrFail();

            $chatRequest->block();
            $chatRequest->load('sender', 'recipient');

            return response()->json([
                'success' => true,
                'message' => 'User blocked successfully',
                'data' => $chatRequest,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Failed to block user', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to block user: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a message for the current user.
     *
     * DELETE /api/v1/chat/messages/{messageId}
     */
    public function deleteMessage(int $messageId): JsonResponse
    {
        try {
            $authUser = Auth::user();

            $message = Chat::findOrFail($messageId);

            // Check if user owns this message
            if ($message->from_user_id === $authUser->id) {
                $message->update(['deleted_by' => 'sender', 'deleted_at_from' => now()]);
            } elseif ($message->to_user_id === $authUser->id) {
                $message->update(['deleted_at_to' => now()]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this message',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Message deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Failed to delete message', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete message: '.$e->getMessage(),
            ], 500);
        }
    }
}
