<?php

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
    // Direct messages
    Route::post('/messages', [DirectChatController::class, 'sendMessage'])
        ->name('chat.send-message');
    Route::delete('/messages/{messageId}', [DirectChatController::class, 'deleteMessage'])
        ->name('chat.delete-message');
    Route::post('/mark-as-read', [DirectChatController::class, 'markAsRead'])
        ->name('chat.mark-as-read');

    // Conversations
    Route::get('/conversations', [DirectChatController::class, 'getConversations'])
        ->name('chat.get-conversations');
    Route::get('/conversations/{userId}', [DirectChatController::class, 'getConversation'])
        ->name('chat.get-conversation');
    Route::get('/conversations/{userId}/messages', [DirectChatController::class, 'getMessages'])
        ->name('chat.get-messages');

    // Create conversation (supports direct chats OR AI conversations with assessment context)
    Route::post('/conversations', [\App\Http\Controllers\API\V1\Chat\ChatController::class, 'createConversation'])
        ->name('chat.create-conversation');

    // Analyze assessments for AI-chat context
    Route::post('/analyze-assessments', [\App\Http\Controllers\API\V1\Chat\ChatController::class, 'analyzeAssessments'])
        ->name('chat.analyze-assessments');

    // Chat requests
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
