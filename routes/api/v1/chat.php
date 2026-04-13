<?php

use App\Http\Controllers\API\V1\Chat\ChatController;
use App\Http\Controllers\API\V1\Chat\DirectChatController;
use Illuminate\Support\Facades\Route;

/**
 * Direct Chat Routes
 *
 * Routes for direct user-to-user messaging, chat requests, and presence tracking.
 * All routes require authentication via Sanctum.
 *
 * Base path: /api/v1/chat
 */
Route::middleware(['auth:sanctum'])->group(function () {
    // Conversations (canonical conversation model)
    Route::get('/conversations', [ChatController::class, 'getConversations'])
        ->name('chat.conversations.index');
    Route::post('/conversations', [ChatController::class, 'createConversation'])
        ->name('chat.conversations.create');
    Route::get('/conversations/{conversation}/messages', [ChatController::class, 'getConversation'])
        ->name('chat.conversations.messages.index');
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'sendMessage'])
        ->name('chat.conversations.messages.create');
    Route::post('/conversations/{conversation}/mark-read', [ChatController::class, 'markAsRead'])
        ->name('chat.conversations.mark-read');

    // Messages
    Route::delete('/messages/{message}', [ChatController::class, 'deleteMessage'])
        ->name('chat.messages.delete');

    // Analyze assessments for AI-chat context
    Route::post('/analyze-assessments', [ChatController::class, 'analyzeAssessments'])
        ->name('chat.analyze-assessments');

    // Legacy direct-chat endpoints (kept for backwards compatibility)
    Route::prefix('direct')->group(function () {
        Route::post('/messages', [DirectChatController::class, 'sendMessage'])
            ->name('chat.direct.send-message');
        Route::delete('/messages/{messageId}', [DirectChatController::class, 'deleteMessage'])
            ->name('chat.direct.delete-message');
        Route::post('/mark-as-read', [DirectChatController::class, 'markAsRead'])
            ->name('chat.direct.mark-as-read');
        Route::get('/conversations', [DirectChatController::class, 'getConversations'])
            ->name('chat.direct.get-conversations');
        Route::get('/conversations/{userId}', [DirectChatController::class, 'getConversation'])
            ->name('chat.direct.get-conversation');
        Route::get('/conversations/{userId}/messages', [DirectChatController::class, 'getMessages'])
            ->name('chat.direct.get-messages');
    });

    // Chat requests (legacy direct-chat)
    Route::post('/requests', [DirectChatController::class, 'sendChatRequest'])
        ->name('chat.send-request');
    Route::get('/requests/pending', [DirectChatController::class, 'getPendingRequests'])
        ->name('chat.get-pending-requests');
    Route::post('/requests/{requestId}/accept', [DirectChatController::class, 'acceptRequest'])
        ->name('chat.accept-request');
    Route::post('/requests/{requestId}/reject', [DirectChatController::class, 'rejectRequest'])
        ->name('chat.reject-request');
    Route::post('/requests/{requestId}/block', [DirectChatController::class, 'blockUser'])
        ->name('chat.block-user');
});
