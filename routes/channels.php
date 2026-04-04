<?php

use App\Models\SupportChat;
use App\Models\TherapySession;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

// Private user channel for DMs
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (string) $user->id === (string) $userId;
});

// Chat for Therapy Sessions
Broadcast::channel('chat.therapysession.{sessionId}', function ($user, $sessionId) {
    // Check if user is participant in the session
    $session = TherapySession::find($sessionId);

    return $session && ($session->patient_id === $user->id || $session->therapist_id === $user->id);
});

// Chat for Groups (Example placeholder)
Broadcast::channel('chat.group.{groupId}', function ($user, $groupId) {
    // return $user->groups->contains($groupId);
    return true; // Implement actual check
});

// Presence channel for global/online status
Broadcast::channel('online', function ($user) {
    return [
        'id' => $user->id,
        'name' => $user->first_name.' '.$user->last_name,
        'role' => $user->role_id, // Assuming role_id exists
        'avatar' => $user->profile_photo,
    ];
});

// ── Support Live Chat ────────────────────────────────────────────────────────

// Customer's own chat room — authenticated user who owns the chat OR any agent
Broadcast::channel('support.chat.{uuid}', function ($user, $uuid) {
    $chat = SupportChat::where('uuid', $uuid)->first();
    if (! $chat) {
        return false;
    }
    // Chat owner (authenticated patient)
    if ($chat->user_id && (string) $chat->user_id === (string) $user->id) {
        return true;
    }
    // Any support agent or admin
    if ($user->hasAnyRole(['support', 'admin', 'clinical_manager', 'ceo'])) {
        return true;
    }

    return false;
});

// Agents-only broadcast channel — all open/waiting chats go here
Broadcast::channel('support.agents', function ($user) {
    if (! $user->hasAnyRole(['support', 'admin', 'clinical_manager', 'ceo'])) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => trim($user->first_name.' '.$user->last_name),
        'avatar' => $user->profile_photo,
    ];
});
