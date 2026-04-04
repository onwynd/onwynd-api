<?php

namespace App\Http\Controllers\API\V1\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\CreateConversationRequest;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    /**
     * Get all conversations for user
     *
     * GET /api/v1/conversations
     */
    public function getConversations(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $perPage = $request->get('per_page', 20);
            $sortBy = $request->get('sort_by', 'latest'); // latest, oldest, unread

            Log::info('Chat: Get conversations', [
                'user_id' => $user->id,
                'sort_by' => $sortBy,
            ]);

            $query = Conversation::where(function ($query) use ($user) {
                $query->where('initiator_id', $user->id)
                    ->orWhere('recipient_id', $user->id);
            })->with('initiator', 'recipient');

            // Apply sorting
            if ($sortBy === 'latest') {
                $query->orderByDesc('last_message_at');
            } elseif ($sortBy === 'oldest') {
                $query->orderBy('last_message_at');
            } elseif ($sortBy === 'unread') {
                $query->where(function ($q) use ($user) {
                    $q->where('initiator_id', $user->id)
                        ->where('unread_recipient_count', '>', 0)
                        ->orWhere('recipient_id', $user->id)
                        ->where('unread_initiator_count', '>', 0);
                })->orderByDesc('last_message_at');
            }

            $conversations = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Conversations retrieved',
                'data' => [
                    'conversations' => $conversations->map(fn ($conversation) => [
                        'id' => $conversation->id,
                        'other_user' => [
                            'id' => $conversation->initiator_id === $user->id
                                ? $conversation->recipient->id
                                : $conversation->initiator->id,
                            'name' => $conversation->initiator_id === $user->id
                                ? $conversation->recipient->full_name
                                : $conversation->initiator->full_name,
                            'role' => $conversation->initiator_id === $user->id
                                ? $conversation->recipient->role
                                : $conversation->initiator->role,
                            'avatar_url' => $conversation->initiator_id === $user->id
                                ? $conversation->recipient->avatar_url
                                : $conversation->initiator->avatar_url,
                        ],
                        'last_message' => $conversation->last_message,
                        'last_message_at' => $conversation->last_message_at,
                        'unread_count' => $conversation->initiator_id === $user->id
                            ? $conversation->unread_recipient_count
                            : $conversation->unread_initiator_count,
                        'is_blocked' => $conversation->initiator_id === $user->id
                            ? $conversation->blocked_by_recipient
                            : $conversation->blocked_by_initiator,
                        'created_at' => $conversation->created_at,
                    ]),
                    'pagination' => [
                        'total' => $conversations->total(),
                        'count' => $conversations->count(),
                        'per_page' => $conversations->perPage(),
                        'current_page' => $conversations->currentPage(),
                        'last_page' => $conversations->lastPage(),
                    ],
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Chat: Get conversations failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversations',
            ], 400);
        }
    }

    /**
     * Create a new conversation
     *
     * POST /api/v1/conversations
     */
    public function createConversation(CreateConversationRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // If assessment_result_ids provided, create an AI-context conversation
            if ($request->has('assessment_result_ids') && is_array($request->assessment_result_ids)) {
                $assessmentIds = $request->assessment_result_ids;

                Log::info('Chat: Creating AI conversation with assessment context', [
                    'user_id' => $user->id,
                    'assessment_result_ids' => $assessmentIds,
                ]);

                // Create a ChatConversation (chat_conversations table) for AI sessions
                $chatConv = \App\Models\ChatConversation::create([
                    'user_id' => $user->id,
                    'title' => $request->title ?? null,
                    'ai_personality' => $request->ai_personality ?? 'supportive',
                    'context_data' => null,
                ]);

                // Attach only valid assessment IDs that belong to the user
                $valid = \App\Models\UserAssessmentResult::whereIn('id', $assessmentIds)
                    ->where('user_id', $user->id)
                    ->pluck('id')
                    ->toArray();

                if (! empty($valid)) {
                    $chatConv->assessments()->attach($valid);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Conversation created',
                    'data' => ['conversation_id' => $chatConv->id],
                ], 201);
            }

            $recipientId = $request->recipient_id;

            Log::info('Chat: Creating conversation', [
                'initiator_id' => $user->id,
                'recipient_id' => $recipientId,
            ]);

            // Check if conversation already exists
            $existingConversation = Conversation::where(function ($query) use ($user, $recipientId) {
                $query->where('initiator_id', $user->id)
                    ->where('recipient_id', $recipientId)
                    ->orWhere('initiator_id', $recipientId)
                    ->where('recipient_id', $user->id);
            })->first();

            if ($existingConversation) {
                return response()->json([
                    'success' => true,
                    'message' => 'Conversation already exists',
                    'data' => ['conversation_id' => $existingConversation->id],
                ]);
            }

            $conversation = Conversation::create([
                'initiator_id' => $user->id,
                'recipient_id' => $recipientId,
                'last_message' => null,
                'last_message_at' => null,
                'unread_initiator_count' => 0,
                'unread_recipient_count' => 0,
            ]);

            Log::info('Chat: Conversation created', ['conversation_id' => $conversation->id]);

            return response()->json([
                'success' => true,
                'message' => 'Conversation created',
                'data' => ['conversation_id' => $conversation->id],
            ], 201);

        } catch (Exception $e) {
            Log::error('Chat: Create conversation failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create conversation: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Analyze assessments and return AI insights
     *
     * POST /api/v1/chat/analyze-assessments
     */
    public function analyzeAssessments(Request $request): JsonResponse
    {
        $request->validate([
            'assessment_result_ids' => 'required|array|min:1',
            'assessment_result_ids.*' => 'integer',
        ]);

        $user = Auth::user();

        $results = \App\Models\UserAssessmentResult::whereIn('id', $request->assessment_result_ids)
            ->where('user_id', $user->id)
            ->get();

        if ($results->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No assessment results found.'], 404);
        }

        // Aggregate a brief summary for the AI service
        $summaries = $results->map(fn ($r) => [
            'id' => $r->id,
            'assessment' => $r->assessment?->title ?? 'assessment',
            'score' => $r->total_score,
            'severity' => $r->severity_level,
        ])->toArray();

        try {
            $analysisTexts = [];
            $insights = [];

            // Use the AI service to generate interpretations for each assessment and combine
            foreach ($results as $r) {
                $ai = app(\App\Services\AI\OpenAIService::class)->generateAssessmentInterpretation($r->assessment?->title ?? 'assessment', $r->total_score, $r->answers ?? []);
                $analysisTexts[] = $ai['interpretation'] ?? '';
                $insights = array_merge($insights, $ai['recommendations'] ?? []);
            }

            $combinedAnalysis = implode("\n", array_filter($analysisTexts));

            return response()->json([
                'success' => true,
                'message' => 'Analysis generated',
                'data' => [
                    'analysis' => $combinedAnalysis,
                    'insights' => array_values(array_slice(array_unique($insights), 0, 10)),
                    'summaries' => $summaries,
                ],
            ]);
        } catch (Exception $e) {
            // Fallback: return lightweight analysis
            return response()->json([
                'success' => true,
                'message' => 'Analysis generated (fallback)',
                'data' => [
                    'analysis' => 'User shows mixed trends across selected assessments.',
                    'insights' => ['Check sleep hygiene', 'Monitor changes over 30 days'],
                    'summaries' => $summaries,
                ],
            ]);
        }
    }

    /**
     * Get conversation messages
     *
     * GET /api/v1/conversations/{conversation}/messages
     */
    public function getConversation(Conversation $conversation, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Verify user has access to this conversation
            if ($conversation->initiator_id !== $user->id && $conversation->recipient_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            // Check if conversation is blocked
            if ($conversation->isBlocked($user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This conversation has been blocked',
                ], 403);
            }

            $perPage = $request->get('per_page', 50);

            Log::info('Chat: Get conversation messages', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
            ]);

            // Mark messages as read
            Message::where('conversation_id', $conversation->id)
                ->where('sender_id', '!=', $user->id)
                ->where('read_at', null)
                ->update(['read_at' => now()]);

            // Reset unread count
            if ($conversation->initiator_id === $user->id) {
                $conversation->update(['unread_initiator_count' => 0]);
            } else {
                $conversation->update(['unread_recipient_count' => 0]);
            }

            $messages = $conversation->messages()
                ->orderByDesc('created_at')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Messages retrieved',
                'data' => [
                    'conversation_id' => $conversation->id,
                    'messages' => $messages->reverse()->map(fn ($message) => [
                        'id' => $message->id,
                        'sender_id' => $message->sender_id,
                        'sender_name' => $message->sender->full_name,
                        'content' => $message->content,
                        'message_type' => $message->message_type,
                        'attachments' => $message->attachments ? json_decode($message->attachments) : [],
                        'read_at' => $message->read_at,
                        'created_at' => $message->created_at,
                    ]),
                    'pagination' => [
                        'total' => $messages->total(),
                        'count' => $messages->count(),
                        'per_page' => $messages->perPage(),
                        'current_page' => $messages->currentPage(),
                        'last_page' => $messages->lastPage(),
                    ],
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Chat: Get conversation failed', [
                'conversation_id' => $conversation->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversation',
            ], 400);
        }
    }

    /**
     * Send message in conversation
     *
     * POST /api/v1/conversations/{conversation}/messages
     */
    public function sendMessage(Conversation $conversation, SendMessageRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($conversation, $request) {
            try {
                $user = Auth::user();

                // Verify access
                if ($conversation->initiator_id !== $user->id && $conversation->recipient_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized',
                    ], 403);
                }

                // Check if blocked
                if ($conversation->isBlocked($user->id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot send message - conversation blocked',
                    ], 403);
                }

                Log::info('Chat: Sending message', [
                    'user_id' => $user->id,
                    'conversation_id' => $conversation->id,
                ]);

                // Create message
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $user->id,
                    'content' => $request->content,
                    'message_type' => $request->message_type ?? 'text',
                    'attachments' => $request->attachments
                        ? json_encode($request->attachments)
                        : null,
                    'read_at' => null,
                ]);

                // Update conversation last message
                $recipientId = $conversation->initiator_id === $user->id
                    ? $conversation->recipient_id
                    : $conversation->initiator_id;

                $isInitiator = $conversation->initiator_id === $user->id;

                $conversation->update([
                    'last_message' => $request->content,
                    'last_message_at' => now(),
                    'unread_'.($isInitiator ? 'recipient' : 'initiator').'_count' => DB::raw('unread_'.($isInitiator ? 'recipient' : 'initiator').'_count + 1'),
                ]);

                Log::info('Chat: Message sent', ['message_id' => $message->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Message sent',
                    'data' => [
                        'message_id' => $message->id,
                        'conversation_id' => $conversation->id,
                        'sender_id' => $user->id,
                        'content' => $message->content,
                        'message_type' => $message->message_type,
                        'created_at' => $message->created_at,
                    ],
                ], 201);

            } catch (Exception $e) {
                Log::error('Chat: Send message failed', [
                    'user_id' => Auth::id(),
                    'conversation_id' => $conversation->id ?? null,
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send message: '.$e->getMessage(),
                ], 400);
            }
        });
    }

    /**
     * Delete message
     *
     * DELETE /api/v1/messages/{message}
     */
    public function deleteMessage(Message $message): JsonResponse
    {
        try {
            $user = Auth::user();

            if ($message->sender_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete message sent by another user',
                ], 403);
            }

            // Allow deletion only within 5 minutes of sending
            if ($message->created_at->diffInMinutes(now()) > 5) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message can only be deleted within 5 minutes of sending',
                ], 400);
            }

            Log::info('Chat: Deleting message', ['message_id' => $message->id]);

            $message->delete();

            Log::info('Chat: Message deleted', ['message_id' => $message->id]);

            return response()->json([
                'success' => true,
                'message' => 'Message deleted',
            ]);

        } catch (Exception $e) {
            Log::error('Chat: Delete message failed', [
                'message_id' => $message->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete message',
            ], 400);
        }
    }

    /**
     * Mark messages as read
     *
     * POST /api/v1/conversations/{conversation}/mark-read
     */
    public function markAsRead(Conversation $conversation): JsonResponse
    {
        try {
            $user = Auth::user();

            if ($conversation->initiator_id !== $user->id && $conversation->recipient_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            Log::info('Chat: Marking messages as read', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
            ]);

            Message::where('conversation_id', $conversation->id)
                ->where('sender_id', '!=', $user->id)
                ->where('read_at', null)
                ->update(['read_at' => now()]);

            // Reset unread count
            if ($conversation->initiator_id === $user->id) {
                $conversation->update(['unread_initiator_count' => 0]);
            } else {
                $conversation->update(['unread_recipient_count' => 0]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Messages marked as read',
            ]);

        } catch (Exception $e) {
            Log::error('Chat: Mark as read failed', [
                'user_id' => Auth::id(),
                'conversation_id' => $conversation->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark as read',
            ], 400);
        }
    }

    /**
     * Get unread message count
     *
     * GET /api/v1/messages/unread/count
     */
    public function getUnreadCount(): JsonResponse
    {
        try {
            $user = Auth::user();

            $count = Conversation::where(function ($query) use ($user) {
                $query->where('initiator_id', $user->id)
                    ->where('unread_initiator_count', '>', 0)
                    ->orWhere('recipient_id', $user->id)
                    ->where('unread_recipient_count', '>', 0);
            })->sum($user->role === 'patient'
                ? 'unread_initiator_count'
                : 'unread_recipient_count');

            return response()->json([
                'success' => true,
                'data' => ['unread_count' => $count],
            ]);

        } catch (Exception $e) {
            Log::error('Chat: Get unread count failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread count',
            ], 400);
        }
    }

    /**
     * Search messages
     *
     * GET /api/v1/messages/search
     */
    public function searchMessages(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = $request->get('q');
            $conversationId = $request->get('conversation_id');
            $perPage = $request->get('per_page', 20);

            if (! $query || strlen($query) < 2) {
                throw new Exception('Search query must be at least 2 characters');
            }

            Log::info('Chat: Searching messages', [
                'user_id' => $user->id,
                'query' => $query,
            ]);

            $messageQuery = Message::whereIn('conversation_id',
                Conversation::where(function ($q) use ($user) {
                    $q->where('initiator_id', $user->id)
                        ->orWhere('recipient_id', $user->id);
                })->pluck('id')
            )->where('content', 'ilike', "%{$query}%");

            if ($conversationId) {
                $messageQuery->where('conversation_id', $conversationId);
            }

            $messages = $messageQuery->orderByDesc('created_at')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Search results',
                'data' => [
                    'results' => $messages->map(fn ($msg) => [
                        'id' => $msg->id,
                        'conversation_id' => $msg->conversation_id,
                        'sender_name' => $msg->sender->full_name,
                        'content' => $msg->content,
                        'created_at' => $msg->created_at,
                    ]),
                    'pagination' => [
                        'total' => $messages->total(),
                        'count' => $messages->count(),
                        'per_page' => $messages->perPage(),
                        'current_page' => $messages->currentPage(),
                        'last_page' => $messages->lastPage(),
                    ],
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Chat: Search failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Search failed: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Block user
     *
     * POST /api/v1/conversations/{conversation}/block
     */
    public function blockUser(Conversation $conversation): JsonResponse
    {
        try {
            $user = Auth::user();

            if ($conversation->initiator_id !== $user->id && $conversation->recipient_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            Log::info('Chat: Blocking user', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
            ]);

            if ($conversation->initiator_id === $user->id) {
                $conversation->update(['blocked_by_initiator' => true]);
            } else {
                $conversation->update(['blocked_by_recipient' => true]);
            }

            return response()->json([
                'success' => true,
                'message' => 'User blocked',
            ]);

        } catch (Exception $e) {
            Log::error('Chat: Block user failed', [
                'user_id' => Auth::id(),
                'conversation_id' => $conversation->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to block user',
            ], 400);
        }
    }

    /**
     * Unblock user
     *
     * POST /api/v1/conversations/{conversation}/unblock
     */
    public function unblockUser(Conversation $conversation): JsonResponse
    {
        try {
            $user = Auth::user();

            if ($conversation->initiator_id !== $user->id && $conversation->recipient_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            Log::info('Chat: Unblocking user', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
            ]);

            if ($conversation->initiator_id === $user->id) {
                $conversation->update(['blocked_by_initiator' => false]);
            } else {
                $conversation->update(['blocked_by_recipient' => false]);
            }

            return response()->json([
                'success' => true,
                'message' => 'User unblocked',
            ]);

        } catch (Exception $e) {
            Log::error('Chat: Unblock user failed', [
                'user_id' => Auth::id(),
                'conversation_id' => $conversation->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unblock user',
            ], 400);
        }
    }
}
