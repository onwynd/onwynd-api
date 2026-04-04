<?php

namespace App\Repositories\Contracts;

interface ChatRepositoryInterface
{
    public function getConversations($userId);

    public function getMessages($conversationId);

    public function sendMessage($conversationId, $senderId, $content);

    public function createConversation(array $participantIds);
}
