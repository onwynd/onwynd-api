<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\AIChat;
use App\Services\AI\OpenAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatController extends BaseController
{
    protected $aiService;

    public function __construct(OpenAIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function index(Request $request)
    {
        // Return messages for a specific session if provided, or list of sessions
        if ($request->has('session_id')) {
            $messages = AIChat::where('user_id', $request->user()->id)
                ->where('session_id', $request->session_id)
                ->orderBy('created_at', 'asc')
                ->get();

            return $this->sendResponse($messages, 'Chat messages retrieved successfully.');
        }

        // Get latest message per session in a single query using a self-join subquery
        $latestIds = AIChat::where('user_id', $request->user()->id)
            ->selectRaw('MAX(id) as id')
            ->groupBy('session_id')
            ->pluck('id');

        $chats = AIChat::whereIn('id', $latestIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($m) => [
                'session_id'   => $m->session_id,
                'last_message' => $m->message,
                'created_at'   => $m->created_at,
            ]);

        return $this->sendResponse($chats, 'Chat history retrieved successfully.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'message' => 'required_without:audio|string|nullable',
            'audio' => 'required_without:message|file|mimes:audio/mpeg,mpga,mp3,wav,m4a,webm|max:10240', // 10MB
            'session_id' => 'nullable|string',
        ]);

        $user = $request->user();
        $sessionId = $request->session_id ?? (string) Str::uuid();
        $messageContent = $request->message;
        $metadata = [];

        // Handle Audio Upload
        if ($request->hasFile('audio')) {
            // Use local storage for MVP as per requirements
            // Structure: documents/patients/{user_id}/chat_audio/{session_id}/
            $path = $request->file('audio')->store("documents/patients/{$user->id}/chat_audio/{$sessionId}", config('filesystems.default'));

            // Generate a temporary URL or just return the path for now (local storage URLs need a route to serve)
            // For MVP, we'll store the path.
            $metadata['audio_path'] = $path;
            $metadata['audio_disk'] = config('filesystems.default');
            // $metadata['audio_url'] = route('document.serve', ['path' => $path]); // If we had a serving route

            // Transcribe audio via Whisper when a key is configured
            $transcriptionService = new \App\Services\AI\TranscriptionService;
            if (! $messageContent && $transcriptionService->isAvailable()) {
                try {
                    $result = $transcriptionService->transcribe($request->file('audio'));
                    $messageContent = $result['text'] ?? null;
                    if ($messageContent) {
                        $metadata['transcribed'] = true;
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Audio transcription failed', [
                        'session_id' => $sessionId,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            if (! $messageContent) {
                $messageContent = '[Audio Message]';
            }
        }

        // Save user message
        $userMessage = AIChat::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'message' => $messageContent,
            'sender' => 'user',
            'metadata' => $metadata,
        ]);

        // Retrieve context (excluding the just added message)
        $history = AIChat::where('session_id', $sessionId)
            ->where('id', '<', $userMessage->id)
            ->orderBy('created_at', 'asc')
            ->take(10)
            ->get()
            ->map(fn ($m) => ['role' => $m->sender === 'user' ? 'user' : 'assistant', 'content' => $m->message])
            ->toArray();

        try {
            $aiResponseText = $this->aiService->generateResponse($messageContent, $history);

            if (! $aiResponseText) {
                $aiResponseText = "I'm having trouble connecting right now. Please try again.";
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AI chat generation failed', [
                'user_id'    => $user->id,
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
            $aiResponseText = 'An error occurred. Please try again later.';
        }

        $aiMessage = AIChat::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'message' => $aiResponseText,
            'sender' => 'ai',
        ]);

        return $this->sendResponse([
            'session_id' => $sessionId,
            'message' => $aiMessage,
            'user_message' => $userMessage,
        ], 'Message sent successfully.');
    }

    public function destroy($sessionId)
    {
        $deleted = AIChat::where('user_id', auth()->id())
            ->where('session_id', $sessionId)
            ->delete();

        if ($deleted === 0) {
            return $this->sendError('Conversation not found or access denied.');
        }

        return $this->sendResponse([], 'Conversation deleted successfully.');
    }

    public function export(Request $request, $sessionId)
    {
        // Validate ownership
        $exists = AIChat::where('user_id', auth()->id())
            ->where('session_id', $sessionId)
            ->exists();

        if (! $exists) {
            return $this->sendError('Conversation not found.');
        }

        $format = $request->query('format', 'json');

        if ($format === 'json') {
            $messages = AIChat::where('session_id', $sessionId)->get();

            return $this->sendResponse($messages, 'Conversation exported.');
        }

        // PDF/TXT export not yet implemented
        return $this->sendError('PDF export is not yet available. Use ?format=json instead.', [], 501);
    }

    public function stats(Request $request)
    {
        $user = $request->user();

        $totalConversations = AIChat::where('user_id', $user->id)
            ->distinct('session_id')
            ->count('session_id');

        $totalMessages = AIChat::where('user_id', $user->id)->count();

        return $this->sendResponse([
            'total_conversations' => $totalConversations,
            'total_messages'      => $totalMessages,
        ], 'Chat statistics retrieved.');
    }
}
