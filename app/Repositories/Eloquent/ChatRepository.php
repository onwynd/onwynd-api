<?php

namespace App\Repositories\Eloquent;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Repositories\Contracts\ChatRepositoryInterface;

class ChatRepository implements ChatRepositoryInterface
{
    public function getConversations($userId)
    {
        return ChatConversation::whereHas('participants', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->with('lastMessage')->get();
    }

    public function getMessages($conversationId)
    {
        return ChatMessage::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function sendMessage($conversationId, $senderId, $content)
    {
        return ChatMessage::create([
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'content' => $content,
            'is_read' => false,
        ]);
    }

    public function createConversation(array $participantIds)
    {
        $conversation = ChatConversation::create();
        $conversation->participants()->attach($participantIds);

        return $conversation;
    }
}
