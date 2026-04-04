<?php

namespace App\Http\Controllers\API\V1\Therapy;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\TypingIndicator;
use App\Http\Controllers\Controller;
use App\Models\Therapy\ChatMessage;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ChatController extends Controller
{
    /**
     * Get chat history
     */
    public function index(Request $request)
    {
        $query = QueryBuilder::for(ChatMessage::class)
            ->allowedFilters([
                AllowedFilter::exact('sender_id'),
                AllowedFilter::exact('receiver_id'),
                AllowedFilter::exact('chatable_id'),
                AllowedFilter::exact('chatable_type'),
                AllowedFilter::exact('type'),
            ])
            ->allowedSorts(['created_at'])
            ->with(['sender:id,first_name,last_name,profile_photo']);

        // Default sort desc
        if (! $request->has('sort')) {
            $query->orderBy('created_at', 'desc');
        }

        // Security check: Ensure user is part of the conversation
        // This is a simplified check. In production, use Policies.
        // For DMs:
        if ($request->has('receiver_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('sender_id', $request->user()->id)
                    ->orWhere('receiver_id', $request->user()->id);
            });
        }
        // For Sessions (chatable):
        // Needs logic to check if user belongs to session.

        return $query->paginate($request->get('per_page', 50));
    }

    /**
     * Send a message
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'receiver_id' => 'nullable|exists:users,id',
            'chatable_id' => 'nullable|uuid',
            'chatable_type' => 'nullable|string',
            'message' => 'nullable|string',
            'type' => 'required|in:text,image,file,system',
            'metadata' => 'nullable|array',
        ]);

        // Validate at least message or metadata exists
        if (empty($validated['message']) && empty($validated['metadata'])) {
            return response()->json(['message' => 'Message or metadata required'], 422);
        }

        $chatMessage = ChatMessage::create([
            'sender_id' => $request->user()->id,
            'receiver_id' => $validated['receiver_id'] ?? null,
            'chatable_id' => $validated['chatable_id'] ?? null,
            'chatable_type' => $validated['chatable_type'] ?? null,
            'message' => $validated['message'],
            'type' => $validated['type'],
            'metadata' => $validated['metadata'] ?? null,
        ]);

        // Broadcast event
        broadcast(new MessageSent($chatMessage))->toOthers();

        return response()->json($chatMessage->load('sender'), 201);
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(Request $request)
    {
        $validated = $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'exists:chat_messages,id',
            'channel_name' => 'required|string',
        ]);

        ChatMessage::whereIn('id', $validated['message_ids'])
            ->where('receiver_id', $request->user()->id)
            ->update(['read_at' => now()]);

        broadcast(new MessageRead(
            $validated['message_ids'],
            $request->user()->id,
            $validated['channel_name']
        ))->toOthers();

        return response()->json(['message' => 'Messages marked as read']);
    }

    /**
     * Send typing indicator
     */
    public function typing(Request $request)
    {
        $validated = $request->validate([
            'channel_name' => 'required|string',
        ]);

        broadcast(new TypingIndicator(
            $request->user()->id,
            $validated['channel_name']
        ))->toOthers();

        return response()->json(['status' => 'ok']);
    }
}
