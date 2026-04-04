<?php

namespace App\Http\Controllers\API\V1\Support;

use App\Events\Support\SupportChatHandedOver;
use App\Events\Support\SupportChatMessageSent;
use App\Http\Controllers\API\BaseController;
use App\Models\SupportChat;
use App\Models\SupportChatMessage;
use App\Services\AI\SupportAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Support Chat Controller
 *
 * Drives the real-time AI → Human support chat at /help/chat.
 *
 * Routes (prefix: /api/v1/support/chats):
 *   POST   /start              → start()         – open a new session
 *   GET    /active             → activeChats()   – agent: all open chats
 *   GET    /{uuid}             → show()          – get chat + messages
 *   POST   /{uuid}/message     → sendMessage()   – customer sends a message
 *   POST   /{uuid}/agent-reply → agentMessage()  – agent replies
 *   POST   /{uuid}/handover    → handover()      – agent claims the chat
 *   POST   /{uuid}/release     → release()       – agent returns to AI mode
 *   POST   /{uuid}/close       → close()         – end the chat
 */
class ChatController extends BaseController
{
    public function __construct(protected SupportAIService $aiService) {}

    // ──────────────────────────────────────────────────────────────────────────
    // CUSTOMER ENDPOINTS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Start a new support chat session.
     */
    public function start(Request $request)
    {
        $user = $request->user();

        $userContext = null;
        if ($user) {
            $userContext = [
                'name' => trim($user->first_name.' '.$user->last_name),
                'email' => $user->email,
                'role' => $user->role ?? 'patient',
                'subscription_plan' => 'Standard',
                'subscription_status' => 'active',
            ];
        }

        $chat = SupportChat::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'session_token' => $user ? null : Str::random(40),
            'status' => 'ai',
            'user_context' => $userContext,
        ]);

        // Send AI greeting
        $firstName = $user ? $user->first_name : 'there';
        $greetingTxt = "Hi {$firstName}! \xF0\x9F\x91\x8B I'm Wynd, your Onwynd support assistant. How can I help you today?";
        $greetingMsg = $this->saveMessage($chat, 'ai', null, $greetingTxt);
        broadcast(new SupportChatMessageSent($greetingMsg));
        $chat->update(['first_response_at' => now()]);

        $this->syncToWyndChat($chat, $greetingMsg);

        return $this->sendResponse([
            'chat_uuid' => $chat->uuid,
            'status' => $chat->status,
            'user_context' => $userContext,
            'messages' => [$this->formatMessage($greetingMsg)],
        ], 'Support chat started.');
    }

    /**
     * Customer sends a message. AI replies unless a human has taken over.
     */
    public function sendMessage(Request $request, string $uuid)
    {
        $request->validate(['message' => 'required|string|max:2000']);

        $chat = SupportChat::where('uuid', $uuid)->firstOrFail();

        if ($chat->status === 'closed') {
            return $this->sendError('This chat session has ended.', [], 422);
        }

        // Save customer message
        $userMsg = $this->saveMessage($chat, 'user', $request->user()?->id, $request->message);
        broadcast(new SupportChatMessageSent($userMsg));
        $this->syncMessageToWyndChat($chat, $userMsg);

        // AI responds only when in AI mode
        if ($chat->isAiMode()) {
            $aiText = $this->aiService->reply($chat, $request->message);
            $needsHandover = $this->aiService->needsHandover($aiText);
            $cleanText = $this->aiService->cleanHandoverFlag($aiText);

            $aiMsg = $this->saveMessage($chat, 'ai', null, $cleanText);
            broadcast(new SupportChatMessageSent($aiMsg));
            $this->syncMessageToWyndChat($chat, $aiMsg);

            if ($needsHandover) {
                $chat->update([
                    'status' => 'waiting',
                    'handover_requested_at' => now(),
                ]);
                broadcast(new SupportChatHandedOver($chat->fresh()));

                $sysMsg = $this->saveMessage($chat, 'system', null, 'Connecting you with a support specialist…');
                broadcast(new SupportChatMessageSent($sysMsg));
            }

            return $this->sendResponse([
                'user_message' => $this->formatMessage($userMsg),
                'ai_reply' => $this->formatMessage($aiMsg),
                'status' => $chat->fresh()->status,
            ], 'Message sent.');
        }

        return $this->sendResponse([
            'user_message' => $this->formatMessage($userMsg),
            'status' => $chat->status,
        ], 'Message sent.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AGENT ENDPOINTS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * List all active chats for the agent dashboard.
     */
    public function activeChats(Request $request)
    {
        $chats = SupportChat::with([
            'user:id,first_name,last_name,email,profile_photo',
            'latestMessage',
            'assignedAgent:id,first_name,last_name',
        ])
            ->whereIn('status', ['ai', 'waiting', 'human'])
            ->orderByRaw("FIELD(status, 'waiting', 'human', 'ai')")
            ->orderBy('updated_at', 'desc')
            ->paginate(30);

        return $this->sendResponse($chats, 'Active chats retrieved.');
    }

    /**
     * Get a single chat with its full message history.
     */
    public function show(string $uuid)
    {
        $chat = SupportChat::with([
            'user:id,first_name,last_name,email,profile_photo',
            'assignedAgent:id,first_name,last_name,profile_photo',
            'messages',
        ])->where('uuid', $uuid)->firstOrFail();

        return $this->sendResponse([
            'chat' => $chat,
            'messages' => $chat->messages->map(fn ($m) => $this->formatMessage($m)),
        ], 'Chat retrieved.');
    }

    /**
     * Agent sends a reply in a human-mode chat.
     */
    public function agentMessage(Request $request, string $uuid)
    {
        $request->validate(['message' => 'required|string|max:4000']);

        $agent = $request->user();
        $chat = SupportChat::where('uuid', $uuid)->firstOrFail();

        if (! in_array($chat->status, ['waiting', 'human'])) {
            return $this->sendError('Take over the chat before replying.', [], 422);
        }

        $msg = $this->saveMessage($chat, 'agent', $agent->id, $request->message);
        broadcast(new SupportChatMessageSent($msg));
        $this->syncMessageToWyndChat($chat, $msg);

        return $this->sendResponse($this->formatMessage($msg), 'Message sent.');
    }

    /**
     * Agent claims the chat — AI stops, human takes over seamlessly.
     */
    public function handover(Request $request, string $uuid)
    {
        $agent = $request->user();
        $chat = SupportChat::where('uuid', $uuid)->firstOrFail();

        if ($chat->status === 'human') {
            return $this->sendError('This chat already has an agent assigned.', [], 422);
        }

        $chat->update([
            'status' => 'human',
            'assigned_agent_id' => $agent->id,
            'handover_at' => now(),
        ]);

        broadcast(new SupportChatHandedOver($chat->fresh()));

        $sysMsg = $this->saveMessage(
            $chat, 'system', null,
            trim($agent->first_name).' from Onwynd Support has joined the chat.'
        );
        broadcast(new SupportChatMessageSent($sysMsg));

        Log::info('Support chat: AI → Human handover', [
            'chat_uuid' => $uuid,
            'agent_id' => $agent->id,
        ]);

        return $this->sendResponse([
            'chat_uuid' => $uuid,
            'status' => 'human',
            'agent_name' => trim($agent->first_name.' '.$agent->last_name),
        ], 'Chat handed over to agent.');
    }

    /**
     * Agent releases the chat back to AI mode.
     */
    public function release(Request $request, string $uuid)
    {
        $chat = SupportChat::where('uuid', $uuid)->firstOrFail();
        $chat->update([
            'status' => 'ai',
            'assigned_agent_id' => null,
            'handover_at' => null,
        ]);

        broadcast(new SupportChatHandedOver($chat->fresh()));

        return $this->sendResponse(['status' => 'ai'], 'Chat returned to AI mode.');
    }

    /**
     * Close a chat session.
     */
    public function close(string $uuid)
    {
        $chat = SupportChat::where('uuid', $uuid)->firstOrFail();
        $chat->update(['status' => 'closed', 'closed_at' => now()]);

        $sysMsg = $this->saveMessage($chat, 'system', null, 'This chat has been closed. Thank you for contacting Onwynd Support.');
        broadcast(new SupportChatMessageSent($sysMsg));

        return $this->sendResponse(['status' => 'closed'], 'Chat closed.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    protected function saveMessage(SupportChat $chat, string $senderType, ?int $senderId, string $message): SupportChatMessage
    {
        return SupportChatMessage::create([
            'chat_id' => $chat->id,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message' => $message,
        ]);
    }

    protected function formatMessage(SupportChatMessage $msg): array
    {
        return [
            'id' => $msg->id,
            'sender_type' => $msg->sender_type,
            'sender_id' => $msg->sender_id,
            'message' => $msg->message,
            'metadata' => $msg->metadata,
            'is_read' => $msg->is_read,
            'created_at' => $msg->created_at->toIso8601String(),
        ];
    }

    /**
     * Push a new chat session to WyndChat (help.onwynd.com) webhook.
     */
    protected function syncToWyndChat(SupportChat $chat, SupportChatMessage $greeting): void
    {
        $url = rtrim(config('services.wyndchat.url', 'https://help.onwynd.com'), '/').'/webhook/onwynd/chat';
        $secret = config('services.wyndchat.webhook_secret', '');

        try {
            Http::timeout(5)
                ->withHeaders(['X-Onwynd-Signature' => hash_hmac('sha256', $chat->uuid, $secret)])
                ->post($url, [
                    'event' => 'chat.started',
                    'chat_uuid' => $chat->uuid,
                    'user_context' => $chat->user_context,
                    'greeting' => $greeting->message,
                ]);
        } catch (\Exception $e) {
            Log::warning('WyndChat sync failed (chat.started)', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Push a new message to WyndChat for agent visibility.
     */
    protected function syncMessageToWyndChat(SupportChat $chat, SupportChatMessage $msg): void
    {
        $url = rtrim(config('services.wyndchat.url', 'https://help.onwynd.com'), '/').'/webhook/onwynd/chat';
        $secret = config('services.wyndchat.webhook_secret', '');

        try {
            Http::timeout(5)
                ->withHeaders(['X-Onwynd-Signature' => hash_hmac('sha256', $chat->uuid, $secret)])
                ->post($url, [
                    'event' => 'chat.message',
                    'chat_uuid' => $chat->uuid,
                    'sender_type' => $msg->sender_type,
                    'message' => $msg->message,
                    'created_at' => $msg->created_at->toIso8601String(),
                ]);
        } catch (\Exception $e) {
            Log::warning('WyndChat sync failed (chat.message)', ['error' => $e->getMessage()]);
        }
    }
}
