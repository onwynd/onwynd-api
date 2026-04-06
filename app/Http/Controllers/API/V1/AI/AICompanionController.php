<?php

namespace App\Http\Controllers\API\V1\AI;

use App\Events\AI\CompanionRiskEscalationEvent;
use App\Http\Controllers\API\BaseController;
use App\Models\AIChat;
use App\Models\AiConversationSummary;
use App\Models\CrisisEvent;
use App\Models\MoodLog;
use App\Models\UserAssessmentResult;
use App\Services\AI\AICompanionService;
use App\Services\AI\RiskDetectionService;
use App\Services\AiQuotaService;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AICompanionController extends BaseController
{
    private AICompanionService $service;

    public function __construct()
    {
        $this->service = new AICompanionService;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Non-streaming chat endpoint (fallback / internal use)
    // ─────────────────────────────────────────────────────────────────────────
    public function chat(Request $request)
    {
        $request->validate([
            'message'         => 'required|string',
            'conversation_id' => 'nullable|string',
        ]);

        $user   = $request->user();
        $result = $this->service->chat(
            $user,
            (string) $request->string('message'),
            $request->string('conversation_id') ?: null,
            $request->string('language') ?: null
        );

        return $this->sendResponse($result, 'AI Companion response');
    }

    public function getConversation(Request $request, string $conversationId)
    {
        $result = $this->service->getConversation($request->user(), $conversationId);
        return $this->sendResponse($result, 'Conversation retrieved');
    }

    public function deleteConversation(Request $request, string $conversationId)
    {
        $deleted = $this->service->deleteConversation($request->user(), $conversationId);
        if (! $deleted) {
            return $this->sendError('Conversation not found', [], 404);
        }
        return $this->sendResponse(['deleted' => true], 'Conversation deleted');
    }

    public function getConversations(Request $request)
    {
        $result = $this->service->getConversations($request->user());
        return $this->sendResponse($result, 'Conversations retrieved');
    }

    public function getCompanionNotes(Request $request)
    {
        $user  = $request->user();
        $prefs = $user->preferences ?? [];
        $notes = $prefs['companion_notes'] ?? [];
        return $this->sendResponse($notes, 'Companion notes retrieved');
    }

    public function updateCompanionNotes(Request $request)
    {
        $request->validate([
            'hobbies'                => 'sometimes|array',
            'hobbies.*'              => 'string|max:100',
            'favorite_foods'         => 'sometimes|array',
            'favorite_foods.*'       => 'string|max:100',
            'activities'             => 'sometimes|array',
            'activities.*'           => 'string|max:100',
            'notes'                  => 'sometimes|array',
            'notes.*'                => 'string|max:500',
            'life_situation'         => 'sometimes|array',
            'life_situation.*'       => 'nullable|string|max:300',
            'milestones'             => 'sometimes|array',
            'milestones.*'           => 'string|max:300',
            'personal_details'       => 'sometimes|array',
            'personal_details.*'     => 'nullable|string|max:100',
        ]);

        $user     = $request->user();
        $prefs    = $user->preferences ?? [];
        $existing = $prefs['companion_notes'] ?? [
            'hobbies'        => [],
            'favorite_foods' => [],
            'activities'     => [],
            'notes'          => [],
            'life_situation' => [],
            'milestones'     => [],
            'personal_details' => [],
        ];

        $merge = fn (array $old, array $new): array =>
            array_values(array_unique(array_merge($old, $new)));

        if ($request->has('hobbies'))        { $existing['hobbies']        = $merge($existing['hobbies'] ?? [],        $request->hobbies); }
        if ($request->has('favorite_foods')) { $existing['favorite_foods'] = $merge($existing['favorite_foods'] ?? [], $request->favorite_foods); }
        if ($request->has('activities'))     { $existing['activities']     = $merge($existing['activities'] ?? [],     $request->activities); }
        if ($request->has('notes'))          { $existing['notes']          = $merge($existing['notes'] ?? [],          $request->notes); }

        if ($request->has('life_situation') && is_array($request->life_situation)) {
            $existingLife = is_array($existing['life_situation'] ?? null) ? $existing['life_situation'] : [];
            foreach ($request->life_situation as $key => $value) {
                if (is_string($key) && preg_match('/^[a-z_]{1,30}$/', $key)) {
                    if (empty($value)) {
                        unset($existingLife[$key]);
                    } else {
                        $existingLife[$key] = mb_substr((string) $value, 0, 300);
                    }
                }
            }
            $existing['life_situation'] = $existingLife;
        }

        if ($request->has('milestones') && is_array($request->milestones)) {
            $existingMilestones = is_array($existing['milestones'] ?? null) ? $existing['milestones'] : [];
            foreach ($request->milestones as $milestone) {
                if (is_string($milestone) && ! empty(trim($milestone))) {
                    $existingMilestones[] = mb_substr(trim($milestone), 0, 300);
                }
            }
            $existing['milestones'] = array_values(array_slice($existingMilestones, -20));
        }

        if ($request->has('personal_details') && is_array($request->personal_details)) {
            $existingPersonal = is_array($existing['personal_details'] ?? null) ? $existing['personal_details'] : [];
            foreach ($request->personal_details as $key => $value) {
                if (is_string($key) && preg_match('/^[a-z_]{1,30}$/', $key)) {
                    if (empty($value)) {
                        unset($existingPersonal[$key]);
                    } else {
                        $existingPersonal[$key] = mb_substr((string) $value, 0, 100);
                    }
                }
            }
            $existing['personal_details'] = $existingPersonal;
        }

        $existing['last_updated']  = now()->toISOString();
        $prefs['companion_notes']  = $existing;
        $user->update(['preferences' => $prefs]);

        return $this->sendResponse($existing, 'Companion notes updated');
    }

    public function quotaStatus(Request $request)
    {
        $status = (new AiQuotaService)->getStatus($request->user());
        return $this->sendResponse($status, 'Quota status retrieved');
    }

    /**
     * Record thumbs-up / thumbs-down feedback on a single AI message.
     */
    public function feedback(Request $request, int $chatId)
    {
        $request->validate(['vote' => 'required|in:up,down']);

        $user = $request->user();
        $chat = AIChat::where('id', $chatId)
            ->where('user_id', $user->id)
            ->where('sender', 'ai')
            ->firstOrFail();

        $score = $request->input('vote') === 'up' ? 1 : -1;
        $meta  = $chat->metadata ?? [];
        $meta['user_feedback'] = $request->input('vote');

        $chat->update(['sentiment_score' => $score, 'metadata' => $meta]);

        return $this->sendResponse(['vote' => $request->input('vote')], 'Feedback recorded');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STREAMING ENDPOINT  (primary — used by the web frontend)
    // ─────────────────────────────────────────────────────────────────────────
    public function stream(Request $request)
    {
        $request->validate([
            'message'         => 'required|string',
            'conversation_id' => 'nullable|string',
        ]);

        $user              = $request->user();
        $message           = (string) $request->string('message');
        $conversationId    = $request->string('conversation_id') ?: null;
        $sessionId         = $conversationId ?: (string) Str::uuid();
        $preferredLanguage = (string) ($request->string('language') ?: ($user->language ?? 'en'));

        // ── Risk analysis ────────────────────────────────────────────────────
        $risk         = (new RiskDetectionService)->analyze($message);
        $quotaService = new AiQuotaService;

        if (in_array($risk['risk_level'] ?? 'none', ['high', 'severe'])) {
            $quotaService->setDistressFlag($user);

            CrisisEvent::create([
                'uuid'             => (string) Str::uuid(),
                'user_id'          => $user->id,
                'org_id'           => $user->organization_membership?->organization_id ?? null,
                'session_id'       => $sessionId,
                'risk_level'       => $risk['risk_level'],
                'triggered_at'     => now(),
                'resources_shown'  => true,
                'banner_shown'     => true,
                'override_active'  => true,
            ]);

            $timestamp     = now()->toDateTimeString();
            $hashedSession = substr(hash('sha256', $sessionId), 0, 12);
            $recipient     = config('onwynd.crisis_email', 'clinical@onwynd.com');

            try {
                Mail::raw(
                    "A user on the Onwynd platform triggered a crisis detection flag at {$timestamp}.\n"
                    . "The user has been shown emergency resources.\n"
                    . "Session ID: {$hashedSession} (hashed).\n"
                    . "No further action required unless escalated by the user.",
                    fn ($m) => $m->to($recipient)->subject('Crisis flag triggered — Onwynd platform')
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send crisis email', ['error' => $e->getMessage()]);
            }
        }

        // ── Crisis bypass — stream emergency message immediately ──────────────
        $bypassOnRisk = (bool) config('services.ai.bypass_llm_on_risk', true);
        if ($bypassOnRisk && $risk['requires_escalation']) {
            AIChat::create([
                'user_id'    => $user->id,
                'session_id' => $sessionId,
                'message'    => $message,
                'sender'     => 'user',
                'metadata'   => ['source' => 'companion'],
            ]);

            event(new CompanionRiskEscalationEvent($user->id, $sessionId, $message, $risk));
            $safety = $this->buildEmergencyMessageForUser($user);

            return response()->stream(function () use ($user, $sessionId, $safety, $risk) {
                $this->sseStart(['is_distress' => true]);
                $this->sseData($safety);
                $this->sseEnd();

                AIChat::create([
                    'user_id'    => $user->id,
                    'session_id' => $sessionId,
                    'message'    => $safety,
                    'sender'     => 'ai',
                    'risk_level' => $risk['risk_level'],
                    'metadata'   => ['source' => 'companion', 'stream' => true, 'risk' => $risk],
                ]);
            }, 200, $this->sseHeaders());
        }

        // ── Resolve AI provider ───────────────────────────────────────────────
        [$apiKey, $model, $baseUrl] = $this->resolveProvider();

        // ── Build system prompt ───────────────────────────────────────────────
        $system = $this->buildSystemPrompt($user, $sessionId, $preferredLanguage);

        // ── Identity short-circuit ────────────────────────────────────────────
        $firstName = $user->first_name ?? explode(' ', $user->name ?? 'there')[0];
        $langPref  = strtolower($preferredLanguage) ?: 'en';

        if ($this->isIdentityQuestion($message)) {
            $reply = $this->identityReply($firstName, $langPref);

            return response()->stream(function () use ($user, $sessionId, $reply) {
                $this->sseStart();
                $this->sseData($reply);
                $this->sseEnd();

                $clean = $this->stripTags($reply);
                AIChat::create([
                    'user_id'    => $user->id,
                    'session_id' => $sessionId,
                    'message'    => $clean,
                    'sender'     => 'ai',
                    'metadata'   => ['source' => 'companion', 'stream' => true],
                ]);
            }, 200, $this->sseHeaders());
        }

        // ── Persist user message ──────────────────────────────────────────────
        AIChat::create([
            'user_id'    => $user->id,
            'session_id' => $sessionId,
            'message'    => $message,
            'sender'     => 'user',
            'metadata'   => ['source' => 'companion'],
        ]);

        // ── Load recent conversation history (last 20 turns) ──────────────────
        $history = AIChat::where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->orderBy('created_at', 'asc')
            ->limit(20)
            ->get(['sender', 'message'])
            ->map(fn ($m) => [
                'role'    => $m->sender === 'ai' ? 'assistant' : 'user',
                'content' => $m->message,
            ])
            ->toArray();

        $payload = [
            'model'      => $model,
            'stream'     => true,
            'messages'   => array_merge(
                [['role' => 'system', 'content' => $system]],
                $history,
                [['role' => 'user', 'content' => $message]],
            ),
            'temperature' => 0.7,
            'max_tokens'  => 600,   // FIX: prevents mid-word truncation
        ];

        $isDistress = in_array($risk['risk_level'] ?? 'none', ['high', 'severe']);

        return response()->stream(
            function () use ($apiKey, $baseUrl, $payload, $user, $sessionId, $risk, $isDistress) {
                $this->streamFromProvider($apiKey, $baseUrl, $payload, $user, $sessionId, $risk, $isDistress);
            },
            200,
            $this->sseHeaders()
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Core streaming loop — with tag-suppression buffer
    // ─────────────────────────────────────────────────────────────────────────
    private function streamFromProvider(
        string $apiKey,
        string $baseUrl,
        array  $payload,
        $user,
        string $sessionId,
        array  $risk,
        bool   $isDistress
    ): void {
        $this->sseStart(['is_distress' => $isDistress]);

        $assistantText = '';  // full raw accumulator (including tags — for DB)
        $buffer        = '';  // clean display buffer (tags suppressed)
        $inTag         = false;

        // Tag openers to suppress from the live stream
        $tagOpeners = ['[NOTED:', '[THERAPIST_RECOMMEND:'];

        try {
            $client   = new Client([
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
            ]);
            $response = $client->request('POST', $baseUrl . '/chat/completions', [
                'json'   => $payload,
                'stream' => true,
            ]);

            $body = $response->getBody();

            while (! $body->eof()) {
                $chunk = $body->read(512);   // smaller reads = lower latency per token
                if ($chunk === '') {
                    usleep(30000);
                    continue;
                }

                $lines = preg_split('/\r?\n/', $chunk);

                foreach ($lines as $line) {
                    if (! str_starts_with($line, 'data:')) {
                        continue;
                    }
                    $json = trim(substr($line, 5));
                    if ($json === '[DONE]') {
                        continue;
                    }
                    $data  = json_decode($json, true);
                    $delta = data_get($data, 'choices.0.delta.content');
                    if (! $delta) {
                        continue;
                    }

                    $assistantText .= $delta;
                    $buffer        .= $delta;

                    // ── Tag-suppression logic ────────────────────────────────
                    if ($inTag) {
                        // We are inside a tag block — look for closing ]
                        // A tag ends at the first ] that closes the JSON object
                        $closePos = $this->findTagClose($buffer);
                        if ($closePos !== false) {
                            // Tag is now complete — drop it, flush anything after
                            $buffer  = ltrim(substr($buffer, $closePos + 1));
                            $inTag   = false;
                            if ($buffer !== '') {
                                $this->sseData($buffer);
                                $buffer = '';
                            }
                        }
                        // Still inside tag — keep buffering, emit nothing
                        continue;
                    }

                    // Check if buffer now contains the start of a tag
                    $tagPos = $this->findTagOpener($buffer, $tagOpeners);

                    if ($tagPos !== false) {
                        // Emit everything before the tag opener
                        $safe = substr($buffer, 0, $tagPos);
                        if ($safe !== '') {
                            $this->sseData($safe);
                        }
                        $buffer = substr($buffer, $tagPos);
                        $inTag  = true;

                        // Check if the tag is already complete in this buffer
                        $closePos = $this->findTagClose($buffer);
                        if ($closePos !== false) {
                            $buffer = ltrim(substr($buffer, $closePos + 1));
                            $inTag  = false;
                            if ($buffer !== '') {
                                $this->sseData($buffer);
                                $buffer = '';
                            }
                        }
                        continue;
                    }

                    // No tag in buffer — safe to stream
                    // But hold back the last 30 chars in case a tag opener
                    // is split across the next chunk boundary
                    $safeLength = max(0, strlen($buffer) - 30);
                    if ($safeLength > 0) {
                        $this->sseData(substr($buffer, 0, $safeLength));
                        $buffer = substr($buffer, $safeLength);
                    }
                }
            }

            // ── Flush any remaining clean buffer content ─────────────────────
            if (! $inTag && $buffer !== '') {
                $this->sseData($buffer);
                $buffer = '';
            }

        } catch (\Throwable $e) {
            Log::error('AI stream error', ['error' => $e->getMessage(), 'session' => $sessionId]);
            $this->sseEvent('error', ['message' => 'stream_failed']);
        }

        $this->sseEnd();

        // ── Persist the full response (with tags stripped for DB) ────────────
        if ($assistantText !== '') {
            $clean      = $this->stripTags($assistantText);
            $chatRecord = AIChat::create([
                'user_id'    => $user->id,
                'session_id' => $sessionId,
                'message'    => trim($clean),
                'sender'     => 'ai',
                'metadata'   => [
                    'source' => 'companion',
                    'stream' => true,
                    'risk'   => $risk,
                ],
            ]);

            // Emit ai_chat_id for frontend feedback buttons
            $this->sseEvent('complete', ['ai_chat_id' => $chatRecord->id]);

            // Trigger cross-session summarisation every 10 messages
            $this->maybeSummarise($user, $sessionId);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // System prompt builder
    // ─────────────────────────────────────────────────────────────────────────
    private function buildSystemPrompt($user, string $sessionId, string $preferredLanguage): string
    {
        $firstName   = $user->first_name ?? explode(' ', $user->name ?? 'there')[0];
        $langPref    = strtolower($preferredLanguage) ?: 'en';
        $humanLang   = $this->languageHumanName($langPref);
        $isFirstMsg  = ! AIChat::where('user_id', $user->id)->where('session_id', $sessionId)->exists();

        // AI tone preference
        $tonePref = $user->ai_tone_preference ?? 'warm_nurturing';
        $toneInstruction = match ($tonePref) {
            'clinical_professional' => 'structured, evidence-based, and clinically precise — prioritise clear therapeutic frameworks, avoid filler phrases',
            'motivational'          => 'energetic, solution-focused, and uplifting — celebrate every win, reframe challenges as opportunities',
            'calm_meditative'       => 'calm, gentle, and grounding — use measured pacing, peaceful language, never rush',
            default                 => 'warm, empathetic, and genuinely caring — like a trusted friend who listens without judgment',
        };

        // Assessment context
        $assessmentContext = '';
        $recentAssessments = UserAssessmentResult::where('user_id', $user->id)
            ->with('assessment:id,title,type')
            ->orderBy('completed_at', 'desc')
            ->take(3)
            ->get(['assessment_id', 'total_score', 'severity_level', 'completed_at']);

        if ($recentAssessments->isNotEmpty()) {
            $lines = $recentAssessments->map(function ($r) {
                $title = optional($r->assessment)->title ?? 'Assessment';
                $date  = $r->completed_at ? \Carbon\Carbon::parse($r->completed_at)->diffForHumans() : '';
                $level = $r->severity_level ? " ({$r->severity_level})" : '';
                return "- {$title}: score {$r->total_score}{$level} {$date}";
            })->implode("\n");
            $assessmentContext = "\n\nRecent Assessment Results:\n{$lines}";
        }

        // Mood context
        $moodContext  = '';
        $recentMoods  = [];
        try {
            $recentMoods = MoodLog::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->pluck('mood_score')
                ->toArray();
        } catch (\Throwable) {}

        if (! empty($recentMoods)) {
            $avg     = round(array_sum($recentMoods) / count($recentMoods), 1);
            $latest  = $recentMoods[0] ?? null;
            $trend   = count($recentMoods) >= 2
                ? ($recentMoods[0] >= $recentMoods[count($recentMoods) - 1] ? 'improving' : 'declining')
                : 'stable';
            $moodContext = "\n\nRecent mood scores (1-10): " . implode(', ', $recentMoods)
                . " | Average: {$avg} | Trend: {$trend}";
            if ($latest !== null && $latest <= 4) {
                $moodContext .= ' — user may be struggling, offer extra compassion.';
            } elseif ($latest !== null && $avg >= 7) {
                $moodContext .= ' — user is doing well, affirm their progress!';
            }
        }

        // Goals
        $goals       = collect($user->mental_health_goals ?? [])->filter()->values()->all();
        $goalsContext = ! empty($goals) ? "\nUser's mental health goals: " . implode(', ', $goals) . '.' : '';

        // Companion notes and personal details
        $prefs          = $user->preferences ?? [];
        $companionNotes = $prefs['companion_notes'] ?? [];
        $personalDetails = is_array($companionNotes['personal_details'] ?? null) ? $companionNotes['personal_details'] : [];

        // Birthday check (WAT timezone)
        $todayBirthday = false;
        if (! empty($personalDetails['birthday']) && preg_match('/^\d{2}-\d{2}$/', $personalDetails['birthday'])) {
            $today         = now()->setTimezone('Africa/Lagos')->format('m-d');
            $todayBirthday = ($personalDetails['birthday'] === $today);
        }

        // Build personal context block
        $personalCtx = '';
        if (! empty($companionNotes)) {
            $parts = [];
            if (! empty($companionNotes['hobbies']))        { $parts[] = 'Hobbies: '         . implode(', ', $companionNotes['hobbies']); }
            if (! empty($companionNotes['favorite_foods'])) { $parts[] = 'Favourite foods: ' . implode(', ', $companionNotes['favorite_foods']); }
            if (! empty($companionNotes['activities']))     { $parts[] = 'Likes: '            . implode(', ', $companionNotes['activities']); }
            if (! empty($companionNotes['notes']))          { $parts[] = 'Notes: '            . implode('; ', $companionNotes['notes']); }

            $lifeSituation = is_array($companionNotes['life_situation'] ?? null) ? $companionNotes['life_situation'] : [];
            if (! empty($lifeSituation)) {
                $lifeDetails = array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($lifeSituation), $lifeSituation);
                $parts[]     = 'Current life context — ' . implode(', ', array_filter($lifeDetails));
            }

            $milestones = is_array($companionNotes['milestones'] ?? null) ? $companionNotes['milestones'] : [];
            if (! empty($milestones)) {
                $parts[] = 'Recent milestones: ' . implode('; ', array_slice($milestones, -5));
            }

            if (! empty($personalDetails)) {
                $detailParts = [];
                if (! empty($personalDetails['birthday'])) {
                    try { $bdFormatted = \Carbon\Carbon::createFromFormat('m-d', $personalDetails['birthday'])->format('F j'); }
                    catch (\Throwable) { $bdFormatted = $personalDetails['birthday']; }
                    $detailParts[] = "birthday: {$bdFormatted}";
                }
                foreach (['hometown', 'nickname'] as $k) {
                    if (! empty($personalDetails[$k])) { $detailParts[] = "{$k}: {$personalDetails[$k]}"; }
                }
                if (! empty($detailParts)) { $parts[] = 'Personal details — ' . implode(', ', $detailParts); }
            }

            if (! empty($parts)) {
                $personalCtx = "\n\nWhat I already know about {$firstName}: " . implode('. ', $parts) . '.';
            }
        }

        // Cross-session memory (last 3 summaries)
        $memorySummaries = AiConversationSummary::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->pluck('summary')
            ->reverse()
            ->values();

        $memoryContext = '';
        if ($memorySummaries->isNotEmpty()) {
            $memoryContext = "\n\nPAST CONVERSATION MEMORY (use to feel continuous — reference naturally, not mechanically):\n"
                . $memorySummaries->map(fn ($s, $i) => 'Memory ' . ($i + 1) . ": {$s}")->implode("\n")
                . "\nIf a past memory is relevant to what the user just said, reference it warmly — e.g., \"Last time you mentioned feeling overwhelmed at work — how has that been since?\" Never recite memories back robotically.";
        }

        $firstMsgInstruction = $isFirstMsg
            ? "\n- This is the very first message in this session. Start with a warm, personalised greeting using {$firstName}'s name. Then gently ask what they'd like to focus on today. Offer 2-3 natural topic prompts (e.g., 'Are you dealing with work stress, sleep trouble, or just need someone to talk to?')."
            : '';

        $birthdayInstruction = $todayBirthday
            ? "\n\nBIRTHDAY: Today is {$firstName}'s birthday. Before anything else, open with a warm, heartfelt birthday greeting. Be genuinely celebratory — use their name, wish them a wonderful birthday, and note how meaningful it is that they are here taking care of themselves on this special day. Then continue the conversation naturally."
            : '';

        return <<<SYSTEM
You are Doctor Onwynd — {$toneInstruction}.
The user's name is {$firstName}. Address them naturally and warmly by name.
When they seem low, unmotivated, or struggling, comfort them by name.
Celebrate their wins. Make them feel seen and appreciated.

IDENTITY:
You are the Onwynd AI Companion — a supportive, private mental-health assistant built for Nigerians and Africans. You listen deeply and offer evidence-based suggestions. You are not a replacement for a licensed therapist. If asked whether you are human, be honest: you are an AI.

OUTPUT FORMAT — follow exactly or the app will break:
- Write in plain, flowing prose. No markdown headers (#). No asterisk bullet points unless the user explicitly asks for a list.
- Keep all [NOTED:{...}] and [THERAPIST_RECOMMEND:{...}] tags on their own line at the very end of your message, after a blank line. Never mid-sentence.
- Never end a sentence with a tag. Complete sentence first, then the tag on the next line.
- Never use ALL CAPS. Never abbreviate words. Write in complete sentences at all times.
- Respond in 2-4 sentences for most messages. Only go longer when the user shares something complex or requests detail.

LISTENING FIRST — NON-NEGOTIABLE:
Before you advise, fix, or suggest anything, listen. Acknowledge what the user shared. Validate their feeling. Ask a gentle clarifying question if needed. Never jump straight to solutions.
Examples: "That sounds really hard, {$firstName}. Tell me more about what happened." / "I hear you. That kind of pressure is exhausting."
Always acknowledge before acting.

CULTURAL CONTEXT — NIGERIAN AND AFRICAN REALITIES:
Many users face pressures that standard Western frameworks don't address. Be naturally aware of:
- Economic stress: naira devaluation, ASUU strikes, youth unemployment, hustle culture, "making it" pressure
- Family & community: overbearing relatives, pressure to marry/succeed/send money home, elder authority, communal expectations
- Mental health stigma: many seek help secretly — "I'm fine" often masks real distress; meet them with zero judgment
- Faith & spirituality: Christianity and Islam are central for many users — never dismiss spiritual reframing
- Urban burnout: Lagos traffic, NEPA/power cuts, cost of living, constant hustle fatigue
- Gender expectations: women navigating career and "home duties"; men expected to "be strong"
Acknowledge these realities naturally — not by listing them, but by meeting the user where they are.

CRISIS ROUTING:
If crisis indicators appear (self-harm, suicide, severe hopelessness), immediately provide compassionate emergency guidance. Reference: SURPIN Nigeria: 0800-4357-4673. Never leave a person in crisis without a next step.

CONTEXT:{$goalsContext}{$assessmentContext}{$moodContext}{$personalCtx}{$memoryContext}{$firstMsgInstruction}{$birthdayInstruction}

LANGUAGE:
Reply in {$humanLang}. Use complete, clear sentences. Never drop words or abbreviate.

CBT & NLP TECHNIQUES (use as tools, not scripts):
Apply naturally when the moment calls for them — never as rigid openers:
- Thought challenging: "What's the evidence for and against that thought?"
- Cognitive restructuring: Reframe negatives into balanced perspectives
- Behavioural activation: Suggest small, achievable activities when mood is low
- Gratitude practice: Identify 3 positive things daily
- Self-compassion: Treat yourself as you would a good friend
- Mindfulness: Present-moment awareness without judgment
- Reframing: See situations from different angles

PERSONAL DETAILS CAPTURE:
If the user shares new personal details (hobbies, foods, activities, life context, milestones), include a [NOTED:{...}] tag on its own line at the very end of your response, after a blank line. Only include keys with genuinely new or changed data. Never show any tag to the user mid-sentence.

FORMAT RULES:
- Start the tag with exactly "[NOTED:" — no abbreviations, no variants
- All JSON keys and string values must use "double quotes"
- Close with exactly "]" after the closing brace
- Examples:
  - Nickname: [NOTED:{"personal_details":{"nickname":"Bosco"}}]
  - Birthday: [NOTED:{"personal_details":{"birthday":"06-15"}}]
  - Life context: [NOTED:{"life_situation":{"finances":"got a new job, doing well"}}]
  - Combined: [NOTED:{"hobbies":["yoga"],"milestones":["landed new job at TechCorp"]}]

ASSESSMENT SUGGESTIONS:
If you detect signs of anxiety, depression, burnout, low motivation, sleep issues, or stress, suggest a relevant assessment: PHQ-9 (depression), GAD-7 (anxiety), PSS (stress), ISI (sleep), MBI (burnout), PCL-5 (trauma).

ONWYND ACTIVITY RECOMMENDATIONS:
Recommend relevant Onwynd features using markdown links: [Feature Name](/path)
Available: [Journal](/dashboard/journal), [Mood Check-in](/dashboard/mood), [Breathing Exercise](/unwind), [Mini Meditation](/unwind), [Unwind Hub](/unwind), [Sleep Tracker](/dashboard/sleep), [Exercise Library](/exercise), [Assessments](/assessments), [Book a Therapist](/therapist-booking).
Recommend naturally: "Try our [Breathing Exercise](/unwind) — it really helps in moments like this."

THERAPIST RECOMMENDATIONS:
When the user wants professional help or describes persistent struggles, include at the very end of your message (on its own line after a blank line):
[THERAPIST_RECOMMEND:{"specialization":"<most relevant>","language":"<user language, default en>"}]
Only when genuinely appropriate — never for casual chat.
SYSTEM;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cross-session summarisation (every 10 messages)
    // ─────────────────────────────────────────────────────────────────────────
    private function maybeSummarise($user, string $sessionId): void
    {
        try {
            $messages = AIChat::where('user_id', $user->id)
                ->where('session_id', $sessionId)
                ->orderBy('id')
                ->get(['id', 'message', 'sender']);

            $count = $messages->count();
            if ($count === 0 || $count % 10 !== 0) {
                return;
            }

            $lastSummary = AiConversationSummary::where('user_id', $user->id)
                ->where('session_id', $sessionId)
                ->orderBy('id', 'desc')
                ->first();

            $alreadySummarisedUpTo = $lastSummary?->last_message_id ?? 0;
            $latestMessageId       = $messages->last()->id;

            if ($alreadySummarisedUpTo >= $latestMessageId) {
                return;
            }

            $window     = $messages->slice(-10)->values();
            $transcript = $window->map(fn ($m) => ucfirst($m->sender) . ': ' . $m->message)->implode("\n");

            $summaryPayload = [
                'model'       => config('services.groq.model', 'llama-3.3-70b-versatile'),
                'max_tokens'  => 150,
                'temperature' => 0.3,
                'messages'    => [
                    ['role' => 'system', 'content' => 'You summarise mental health conversations factually and briefly. Use third person. No names. No diagnosis.'],
                    ['role' => 'user',   'content' =>
                        "Summarise this mental health support conversation in exactly 2 sentences.\n"
                        . "Sentence 1: What the user is going through (main theme and emotional state).\n"
                        . "Sentence 2: What was discussed or suggested, and any notable progress or concerns.\n"
                        . "No filler phrases like 'In this conversation...'.\n\n"
                        . $transcript
                    ],
                ],
            ];

            [$apiKey, , $baseUrl] = $this->resolveProvider();

            $client   = new Client(['headers' => ['Authorization' => 'Bearer ' . config('services.groq.api_key'), 'Content-Type' => 'application/json']]);
            $response = $client->post('https://api.groq.com/openai/v1/chat/completions', ['json' => $summaryPayload]);
            $json     = json_decode($response->getBody()->getContents(), true);
            $summary  = data_get($json, 'choices.0.message.content');

            if ($summary) {
                AiConversationSummary::create([
                    'user_id'         => $user->id,
                    'session_id'      => $sessionId,
                    'summary'         => trim($summary),
                    'message_count'   => 10,
                    'last_message_id' => $latestMessageId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Summarisation failed', ['error' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** Returns [apiKey, model, baseUrl] for the configured provider. */
    private function resolveProvider(): array
    {
        $driver = config('services.ai.default', 'openai');

        return match ($driver) {
            'groq' => [
                config('services.groq.api_key'),
                config('services.groq.model', 'llama-3.3-70b-versatile'),
                'https://api.groq.com/openai/v1',
            ],
            'grok' => [
                config('services.grok.api_key'),
                config('services.grok.model', 'grok-1'),
                'https://api.x.ai/v1',
            ],
            default => [
                config('services.openai.api_key'),
                config('services.openai.model', 'gpt-4o-mini'),
                'https://api.openai.com/v1',
            ],
        };
    }

    /**
     * Find the first tag opener position in $buffer.
     * Returns false if none found.
     */
    private function findTagOpener(string $buffer, array $openers): int|false
    {
        $earliest = false;
        foreach ($openers as $opener) {
            $pos = strpos($buffer, $opener);
            if ($pos !== false && ($earliest === false || $pos < $earliest)) {
                $earliest = $pos;
            }
        }
        return $earliest;
    }

    /**
     * Find the closing ] of a tag that starts at position 0 of $buffer.
     * Tracks JSON bracket depth to handle nested arrays/objects.
     * Returns position of the closing ], or false if not yet complete.
     */
    private function findTagClose(string $buffer): int|false
    {
        $depth     = 0;
        $inString  = false;
        $escape    = false;
        $jsonStart = strpos($buffer, '{');

        if ($jsonStart === false) {
            return false;
        }

        for ($i = $jsonStart; $i < strlen($buffer); $i++) {
            $ch = $buffer[$i];

            if ($escape) { $escape = false; continue; }
            if ($ch === '\\' && $inString) { $escape = true; continue; }
            if ($ch === '"') { $inString = ! $inString; continue; }
            if ($inString) { continue; }

            if ($ch === '{' || $ch === '[') { $depth++; }
            elseif ($ch === '}' || $ch === ']') {
                $depth--;
                if ($depth === 0) {
                    // Check if the next non-space char is ]
                    $rest = ltrim(substr($buffer, $i + 1));
                    if (str_starts_with($rest, ']')) {
                        $closePos = $i + 1 + (strlen(substr($buffer, $i + 1)) - strlen($rest));
                        return $closePos;
                    }
                }
            }
        }
        return false;
    }

    /** Strip all [NOTED:{...}] and [THERAPIST_RECOMMEND:{...}] tags from text. */
    private function stripTags(string $text): string
    {
        // Strip complete tags (handles nested brackets via recursive pattern)
        $result = preg_replace('/\[(NOTED|THERAPIST_RECOMMEND):\{(?:[^{}]|(?:\{[^{}]*\}))*\}\]/s', '', $text);
        // Strip [ED:...] debug tags
        $result = preg_replace('/\[ED:.*?\]/s', $result ?? $text);
        return trim($result ?? $text);
    }

    private function isIdentityQuestion(string $text): bool
    {
        $t = strtolower($text);
        return str_contains($t, 'your name')
            || str_contains($t, 'what is your name')
            || str_contains($t, 'who are you');
    }

    private function identityReply(string $firstName, string $lang): string
    {
        return match ($lang) {
            'ig'  => "Ndewo {$firstName}. Aha m bụ Onwynd AI Companion. A bụ m onye ọgụgụ isi dijitalụ nke na-ege gị ntị ma na-enye nkwado na ndụmọdụ dị nro, nke dabere na ọmụmụ ihe. Mkparịta ụka anyị zoro ezo. Kedu ka ị na-eche ugbu a?",
            'yo'  => "Ẹ káàsán {$firstName}. Orúkọ mi ni Onwynd AI Companion. Ẹrọ ọlọ́gbọ́n ni mi tí ń gbọ́ ọ́ àti kí n fún ọ ní ìmọ̀ràn tí ó dá lórí ìmọ̀ ìjìnlẹ̀. Àjùmóṣe wa jẹ́ ìpamọ́. Báwo ni ìmọ̀lára rẹ báyìí?",
            'ha'  => "Sannu {$firstName}. Sunana Onwynd AI Companion. Ni manhaja mai hankali da ke sauraron ka kuma na ba da goyon baya da shawarwari masu tausayi bisa hujjoji. Zancenmu na sirri ne. Yaya kake ji yanzu?",
            'sw'  => "Hujambo {$firstName}. Jina langu ni Onwynd AI Companion. Mimi ni msaidizi wa dijitali anayekusikiliza na kukupa ushauri wa huruma unaotegemea ushahidi. Mazungumzo yetu ni ya faragha. Unajisikia vipi sasa?",
            'tiv' => "Mnger {$firstName}. Mkem we Onwynd AI Companion. Nyian iyol u we u sha yôô u yange u or u ngu sha u tar u nenge u or sha ya. Mluan sha nger a lu u fan. U sha vihi ga?",
            'pcm' => "Hello {$firstName}. My name na Onwynd AI Companion. I be digital helper wey dey listen to you and give gentle, evidence-based support. Our talk dey private. How you dey feel now?",
            default => "Hey {$firstName}. I'm the Onwynd AI Companion — a supportive, private AI assistant that listens and offers gentle, evidence-based suggestions. Our chat is confidential. How are you feeling right now?",
        };
    }

    private function languageHumanName(string $code): string
    {
        return match ($code) {
            'ig'  => 'Igbo',
            'yo'  => 'Yoruba',
            'ha'  => 'Hausa',
            'sw'  => 'Swahili',
            'tiv' => 'Tiv',
            'pcm' => 'Nigerian Pidgin',
            default => 'English',
        };
    }

    private function buildEmergencyMessageForUser($user): string
    {
        $country = strtoupper(substr((string) ($user->country_code ?? $user->country ?? 'NG'), 0, 2));
        $map     = config('emergency.emergency_numbers', []);
        $number  = $map[$country] ?? config('emergency.default_number', '112');

        return "I am detecting serious safety concerns. If you are in immediate danger or thinking about harming yourself, please contact emergency services now. In {$country}, dial {$number}. You can also reach your local crisis line or talk to someone you trust. I am here with you.";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SSE helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function sseHeaders(): array
    {
        return [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];
    }

    private function sseStart(array $meta = []): void
    {
        echo "event: start\n";
        echo 'data: ' . json_encode(array_merge(['status' => 'starting'], $meta)) . "\n\n";
        ob_flush();
        flush();
    }

    private function sseEnd(): void
    {
        echo "event: end\n";
        echo 'data: ' . json_encode(['status' => 'completed']) . "\n\n";
        ob_flush();
        flush();
    }

    private function sseEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        ob_flush();
        flush();
    }

    /**
     * Emit a clean data chunk — replaces newlines with spaces for SSE compatibility.
     */
    private function sseData(string $text): void
    {
        if ($text === '') {
            return;
        }
        echo 'data: ' . str_replace(["\r", "\n"], [' ', ' '], $text) . "\n\n";
        ob_flush();
        flush();
    }
}