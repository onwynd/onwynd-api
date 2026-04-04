<?php

namespace App\Services\AI;

use App\Models\KnowledgeBaseArticle;
use App\Models\SupportChat;
use App\Models\SupportChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * SupportAIService
 *
 * Generates AI responses for the Onwynd Help Center support chat.
 */
class SupportAIService
{
    protected AIProviderFactory $factory;

    public function __construct()
    {
        $this->factory = new AIProviderFactory;
    }

    /**
     * Generate the next AI reply for a support chat.
     *
     * @param  string  $userMessage  The latest message from the customer
     * @return string The AI-generated response
     */
    public function reply(SupportChat $chat, string $userMessage): string
    {
        $provider = $this->factory->makeForTask('simple');

        $messages = $this->buildMessageHistory($chat, $userMessage);

        try {
            $response = $provider->chat($messages, [
                'temperature' => 0.5,
                'max_tokens' => 800,
            ]);

            $cleanResponse = $this->sanitize($response);

            // I2: Check for handover request
            if ($this->needsHandover($cleanResponse)) {
                $chat->update([
                    'handover_requested_at' => now(),
                    'status' => 'pending_handover',
                ]);
            }

            return $cleanResponse;
        } catch (\Exception $e) {
            Log::error('SupportAIService::reply failed', [
                'chat_uuid' => $chat->uuid,
                'error' => $e->getMessage(),
            ]);

            return "I'm sorry, I'm having trouble responding right now. Let me connect you with one of our support agents. [HANDOVER_REQUESTED]";
        }
    }

    /**
     * Build the full message history for the AI provider.
     */
    protected function buildMessageHistory(SupportChat $chat, string $latestUserMessage): array
    {
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $this->buildSystemPrompt($chat)];

        // Load recent history (last 20 messages to stay within context limits)
        $history = SupportChatMessage::where('chat_id', $chat->id)
            ->whereIn('sender_type', ['user', 'ai'])
            ->orderBy('created_at')
            ->take(20)
            ->get();

        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg->sender_type === 'user' ? 'user' : 'assistant',
                'content' => $msg->message,
            ];
        }

        // Add the latest user message
        $messages[] = ['role' => 'user', 'content' => $latestUserMessage];

        return $messages;
    }

    /**
     * Build a support-focused system prompt with user context.
     */
    protected function buildSystemPrompt(SupportChat $chat): string
    {
        $ctx = $chat->user_context ?? [];
        $userName = $ctx['name'] ?? 'there';
        $plan = $ctx['subscription_plan'] ?? 'unknown';
        $role = $ctx['role'] ?? 'patient';

        $contextBlock = '';
        if (! empty($ctx)) {
            $contextBlock = <<<CTX

Customer context (for your reference only — do NOT quote this back verbatim):
- Name: {$userName}
- Role: {$role}
- Subscription plan: {$plan}
- Subscription status: {$ctx['subscription_status']}
CTX;
        }

        // I1: Help Center Context
        // Search for relevant articles based on the last few messages
        $lastMessages = SupportChatMessage::where('chat_id', $chat->id)
            ->orderBy('created_at', 'desc')
            ->take(2)
            ->pluck('message')
            ->toArray();
        $query = implode(' ', $lastMessages);

        $articles = KnowledgeBaseArticle::where('is_published', true)
            ->where(function ($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                    ->orWhere('content', 'LIKE', "%{$query}%");
            })
            ->take(3)
            ->get();

        if ($articles->isNotEmpty()) {
            $contextBlock .= "\n\nRelevant Help Articles:\n";
            foreach ($articles as $article) {
                $contextBlock .= "- {$article->title}: ".mb_substr(strip_tags($article->content), 0, 200)."...\n";
            }
        }

        return <<<PROMPT
You are a helpful, empathetic, and knowledgeable support assistant for Onwynd — a mental health and wellness platform.
Your name is Wynd. You help users with questions about:
- Therapy sessions (booking, joining, cancelling, rescheduling)
- Subscription plans and billing
- The Onwynd AI companion and wellness features (moods, habits, journal, sleep, courses)
- Onwynd Score and gamification
- Account and profile management
- Ambassador and referral program
- Physical wellness centers and check-ins
- Technical issues (app crashes, connectivity, password resets)
- Privacy and data questions
- Institutional/corporate account support

Guidelines:
1. Never identify yourself as an AI, bot, or language model. You are "Wynd from Onwynd Support".
2. Be warm, concise, and solution-oriented. Ground your answers in the provided "Help Articles" if available.
3. If the user has a billing issue, subscription problem, or medical/clinical question — say "Let me get a specialist to help you with this" and end your message with the exact phrase: [HANDOVER_REQUESTED]
4. If the user expresses frustration, urgency, or asks to "speak to a human" — comply immediately and include [HANDOVER_REQUESTED] at the end.
5. Use bullet points for multi-step instructions.
6. For session-related issues, remind users they can reschedule up to 24 hours before the session at no charge.
7. Keep responses under 200 words unless detailed instructions are necessary.
{$contextBlock}
PROMPT;
    }

    /**
     * Check if the AI response signals that a human agent is needed.
     */
    public function needsHandover(string $response): bool
    {
        return str_contains($response, '[HANDOVER_REQUESTED]');
    }

    /**
     * Strip the handover flag from the visible response text.
     */
    public function cleanHandoverFlag(string $response): string
    {
        return trim(str_replace('[HANDOVER_REQUESTED]', '', $response));
    }

    /**
     * Normalise whitespace and remove stray formatting artifacts.
     */
    protected function sanitize(string $content): string
    {
        $content = preg_replace('/\s+/', ' ', $content);

        return trim($content);
    }
}
