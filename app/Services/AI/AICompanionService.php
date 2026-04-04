<?php

namespace App\Services\AI;

use App\Events\AI\CompanionRiskEscalationEvent;
use App\Models\AIChat;
use App\Models\AiConversationSummary;
use App\Models\MoodLog;
use App\Models\SessionNote;
use App\Models\SleepLog;
use App\Models\User;
use App\Models\UserAssessmentResult;
use App\Services\ContactDetectionService;
use App\Services\Recommendation\ContentRecommendationService;
use App\Services\Recommendation\TherapistRecommendationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AICompanionService
{
    public function chat(User $user, string $message, ?string $conversationId = null, ?string $preferredLanguage = null): array
    {
        $sessionId = $conversationId ?: (string) Str::uuid();

        // Block off-platform contact sharing attempts
        $contactTypes = (new ContactDetectionService)->detect($message);
        if (! empty($contactTypes)) {
            return [
                'session_id'        => $sessionId,
                'message'           => "For the safety and privacy of everyone on Onwynd, sharing personal contact details (phone numbers, emails, or social handles) isn't allowed here. All conversations and bookings happen securely through the platform.",
                'contact_blocked'   => true,
                'detected_types'    => $contactTypes,
                'crisis'            => false,
                'recommendations'   => ['therapists' => [], 'articles' => []],
            ];
        }

        $riskService = new RiskDetectionService;

        $driver = config('services.ai.default', 'openai');
        $model = match ($driver) {
            'groq' => config('services.groq.model', 'llama-3.3-70b-versatile'),
            'grok' => config('services.grok.model', 'grok-1'),
            default => config('services.openai.model', 'gpt-4o-mini'),
        };

        $firstName = $user->first_name ?? ($user->name ? explode(' ', $user->name)[0] : 'there');

        // AI tone preference collected during onboarding.
        // Values match the frontend enum: warm_nurturing | clinical_professional | motivational | calm_meditative
        $tonePref = $user->ai_tone_preference ?? 'warm_nurturing';
        $toneInstruction = match ($tonePref) {
            'clinical_professional' => 'structured, evidence-based, and clinically precise — prioritise clear therapeutic frameworks over emotional warmth, avoid filler phrases',
            'motivational' => 'energetic, solution-focused, and uplifting — celebrate every win, reframe challenges as opportunities, use encouraging language',
            'calm_meditative' => 'calm, gentle, and grounding — use measured pacing, peaceful language, and mindfulness-oriented responses; never rush',
            default => 'warm, empathetic, and genuinely caring — like a trusted friend who listens without judgment',
        };

        // Check BEFORE saving the user's message so first-message detection is accurate
        $isFirstMessage = ! AIChat::where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->exists();

        AIChat::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'message' => $message,
            'sender' => 'user',
            'metadata' => [
                'source' => 'companion',
            ],
        ]);

        // Recent assessment results
        $recentAssessments = UserAssessmentResult::where('user_id', $user->id)
            ->with('assessment:id,title,type')
            ->orderBy('completed_at', 'desc')
            ->take(3)
            ->get(['assessment_id', 'total_score', 'severity_level', 'completed_at']);

        $assessmentContext = '';
        if ($recentAssessments->isNotEmpty()) {
            $lines = $recentAssessments->map(function ($r) {
                $title = optional($r->assessment)->title ?? 'Assessment';
                $level = $r->severity_level ? " ({$r->severity_level})" : '';

                return "- {$title}: score {$r->total_score}{$level}";
            })->implode("\n");
            $assessmentContext = "\n\nRecent Assessment Scores:\n{$lines}";
        }

        // Recent mood
        $recentMoods = [];
        try {
            $recentMoods = MoodLog::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->pluck('mood_score')
                ->toArray();
        } catch (\Throwable) {
        }

        $moodContext = '';
        if (! empty($recentMoods)) {
            $avg = round(array_sum($recentMoods) / count($recentMoods), 1);
            $latest = $recentMoods[0] ?? null;
            $trend = count($recentMoods) >= 2
                ? ($recentMoods[0] >= $recentMoods[count($recentMoods) - 1] ? 'improving' : 'declining')
                : 'stable';
            $moodContext = "\nRecent mood (1–10): ".implode(', ', $recentMoods)
                ." | Avg: {$avg} | Trend: {$trend}";
            if ($latest !== null && $latest <= 4) {
                $moodContext .= ' — extra compassion needed.';
            }
        }

        // Recent sleep data
        $recentSleep = [];
        try {
            $recentSleep = SleepLog::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get(['duration_minutes', 'quality_rating', 'created_at'])
                ->map(function ($log) {
                    return [
                        'hours' => round($log->duration_minutes / 60, 1),
                        'quality' => $log->quality_rating,
                        'date' => $log->created_at->format('Y-m-d'),
                    ];
                })
                ->toArray();
        } catch (\Throwable) {
        }

        $sleepContext = '';
        if (! empty($recentSleep)) {
            $avgHours = round(array_sum(array_column($recentSleep, 'hours')) / count($recentSleep), 1);
            $avgQuality = round(array_sum(array_column($recentSleep, 'quality')) / count($recentSleep), 1);
            $latest = $recentSleep[0] ?? null;
            $sleepContext = "\nRecent sleep: {$latest['hours']}h (quality {$latest['quality']}/10) | Avg: {$avgHours}h, quality {$avgQuality}/10";
            if ($avgHours < 6 || $avgQuality < 5) {
                $sleepContext .= ' — consider sleep hygiene tips.';
            }
        }

        // Recent session summaries
        $recentSessionSummaries = SessionNote::whereIn('session_id', function ($query) use ($user) {
            $query->select('id')->from('therapy_sessions')->where('patient_id', $user->id);
        })
            ->where('is_shared_with_patient', true)
            ->orderBy('created_at', 'desc')
            ->take(2)
            ->pluck('session_summary')
            ->toArray();

        $sessionContext = '';
        if (! empty($recentSessionSummaries)) {
            $sessionContext = "\n\nRecent Shared Therapy Summaries:\n- ".implode("\n- ", $recentSessionSummaries);
        }

        $goals = collect($user->mental_health_goals ?? [])->filter()->values()->all();
        $goalsContext = ! empty($goals) ? "\nGoals: ".implode(', ', array_map(fn ($g) => (string) $g, $goals)) : '';
        $langPref = $this->resolveLanguage($user, $message, $preferredLanguage);

        // Companion personal notes
        $prefs = $user->preferences ?? [];
        $companionNotes = $prefs['companion_notes'] ?? [];
        $personalCtx = '';
        if (! empty($companionNotes)) {
            $parts = [];
            if (! empty($companionNotes['hobbies'])) {
                $parts[] = 'Hobbies: '.implode(', ', $companionNotes['hobbies']);
            }
            if (! empty($companionNotes['favorite_foods'])) {
                $parts[] = 'Favourite foods: '.implode(', ', $companionNotes['favorite_foods']);
            }
            if (! empty($companionNotes['activities'])) {
                $parts[] = 'Likes: '.implode(', ', $companionNotes['activities']);
            }
            if (! empty($companionNotes['notes'])) {
                $parts[] = 'Notes: '.implode('; ', $companionNotes['notes']);
            }
            if (! empty($parts)) {
                $personalCtx = "\n\nWhat I know about {$firstName}: ".implode('. ', $parts).'.';
            }
        }

        $firstMsgInstruction = $isFirstMessage
            ? "\nThis is the first message — start with a warm personalised greeting using {$firstName}'s name and gently ask what they'd like to focus on today. Offer 2–3 natural topic prompts."
            : '';

        // Cross-session memory: inject last 3 conversation summaries so Doctor Onwynd
        // remembers the user across sessions (Section 9.2 — most important AI retention feature).
        $memorySummaries = AiConversationSummary::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->pluck('summary')
            ->reverse()
            ->values();

        $memoryContext = '';
        if ($memorySummaries->isNotEmpty()) {
            $memoryContext = "\n\nPAST CONVERSATION MEMORY (earlier sessions — use to feel continuous, not to repeat):\n"
                .$memorySummaries->map(fn ($s, $i) => 'Memory '.($i + 1).": {$s}")->implode("\n");
        }

        $system = <<<SYSTEM
You are Doctor Onwynd — {$toneInstruction}.
The user's name is {$firstName}. Address them naturally and warmly by name.
When they seem low, unmotivated, or struggling, comfort them by name.
Celebrate their wins. Make them feel seen and appreciated.
Keep responses concise (2–4 sentences unless detail is needed), actionable, and encouraging.

IDENTITY:
You are the Onwynd AI Companion — a supportive, private mental-health assistant built for Nigerians and Africans. You listen deeply and offer evidence-based suggestions. You are not a replacement for a licensed therapist. If asked whether you are human, be honest: you are an AI. Do not claim to read files unless they are uploaded for analysis.

LISTENING FIRST — NON-NEGOTIABLE:
Before you advise, fix, or suggest anything, listen. Acknowledge what the user shared. Validate their feeling. Ask a gentle clarifying question if needed. Never jump straight to solutions. A response that skips listening feels cold and mechanical — the opposite of healing.
Examples: "That sounds really hard, {$firstName}. Tell me more about what happened." / "I hear you. That kind of pressure is exhausting."
Always acknowledge before acting.

CULTURAL CONTEXT — NIGERIAN AND AFRICAN REALITIES:
Many users face pressures that standard Western frameworks don't fully address. Be aware:
- Economic stress: naira devaluation, ASUU strikes, youth unemployment, hustle culture, "making it" pressure, fuel prices
- Family & community: overbearing relatives, pressure to marry/succeed/send money home, elder authority, communal expectations
- Mental health stigma: many seek help secretly — "I'm fine" often masks real distress; meet them with zero judgment
- Faith & spirituality: Christianity and Islam are central for many users; spiritual reframing ("God's plan", prayer, community support) can be validating — never dismiss it
- Urban burnout: Lagos traffic, NEPA/power cuts, cost of living, security anxiety, constant hustle fatigue
- Gender expectations: women navigating career and "home duties"; men expected to "be strong" and not show emotion
Acknowledge these realities naturally — not by listing them, but by meeting the user where they are.

CRISIS ROUTING:
If crisis indicators appear (self-harm, suicide, "I want to die", severe hopelessness), immediately provide compassionate emergency guidance. Reference local resources: SURPIN Nigeria: 0800-4357-4673. Never leave a person in crisis without a next step.

CONTEXT:{$goalsContext}{$assessmentContext}{$moodContext}{$sleepContext}{$sessionContext}{$personalCtx}{$memoryContext}{$firstMsgInstruction}

LANGUAGE:
Reply in {$this->languageHumanName($langPref)}. Use complete, clear sentences. Avoid dropping words.

CBT & NLP TECHNIQUES (use as tools, not scripts):
Apply these naturally when the moment calls for them — never as a rigid prefix or opener:
- Thought challenging: "What's the evidence for and against that thought?"
- Cognitive restructuring: Help reframe negatives into balanced perspectives
- Behavioral activation: Suggest small, achievable activities when mood is low
- Problem-solving: Break overwhelming situations into manageable steps
- Gratitude practice: Identify 3 positive things daily
- Self-compassion: Treat yourself as you would a good friend
- Mindfulness: Present-moment awareness without judgment
- Reframing: See situations from different angles
- Future pacing: Visualize successful outcomes
- Meta-model questioning: Clarify vague language and limiting beliefs

PERSONAL DETAILS CAPTURE:
If the user shares new personal details (hobbies, favourite foods, activities, notes, life context), include a metadata tag after your message: [NOTED:{...}] with only changed fields. Never show any tags in user-facing text — the app strips them automatically.

ASSESSMENT SUGGESTIONS:
Detect emotional tone — if you sense anxiety, depression, burnout, low motivation, sleep issues, or stress, suggest a relevant assessment: PHQ-9 (depression), GAD-7 (anxiety), PSS (stress), ISI (sleep), MBI (burnout), PCL-5 (trauma).

ONWYND ACTIVITY RECOMMENDATIONS:
Recommend relevant Onwynd features using markdown links. Format: [Feature Name](/path)
Available: [Journal](/dashboard/journal), [Mood Check-in](/dashboard/mood), [Breathing Exercise](/unwind), [Mini Meditation](/unwind), [Unwind Hub](/unwind), [Gratitude Journal](/dashboard/journal), [Sleep Tracker](/dashboard/sleep), [Exercise Library](/exercise), [Assessments](/assessments).
Recommend naturally: "Try our [Breathing Exercise](/unwind) — it really helps in moments like this."

THERAPIST RECOMMENDATIONS:
When the user wants professional help, describes persistent struggles (prolonged anxiety, depression, trauma, relationship issues, grief, burnout), or asks for a therapist, include at the very end of your message:
[THERAPIST_RECOMMEND:{"specialization":"<most relevant e.g. anxiety, depression, trauma, relationships, grief>","language":"<user preferred language, default en>"}]
Only when genuinely appropriate — not for casual chat or minor stress. Never mention the tag — the app displays real verified therapists automatically.

Preferred language: {$langPref}
SYSTEM;

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $message],
            ],
            'temperature' => 0.7,
        ];

        $assistantText = 'I\'m here for you.';
        $crisis = false;
        $riskLevel = null;
        $riskAnalysis = (new RiskDetectionService)->analyze($message);
        $bypassOnRisk = (bool) config('services.ai.bypass_llm_on_risk', true);
        if ($riskAnalysis['requires_escalation']) {
            event(new CompanionRiskEscalationEvent($user->id, $sessionId, $message, $riskAnalysis));
            $assistantText = $this->buildEmergencyMessageForUser($user);
            $crisis = true;
            $riskLevel = $riskAnalysis['risk_level'];
        }
        $estimatedPromptTokens = $this->estimateTokens($message);
        $estimatedCompletionTokens = 0;

        $recommendations = [
            'therapists' => [],
            'articles' => [],
        ];

        if ($this->isIdentityQuestion($message)) {
            $assistantText = $this->identityReply($firstName, $langPref);
            $estimatedCompletionTokens = $this->estimateTokens($assistantText);
        } elseif (! ($bypassOnRisk && $riskAnalysis['requires_escalation'])) {
            try {
                $json = $this->callWithFallback($payload);
                if ($json) {
                    $assistantText = data_get($json, 'choices.0.message.content') ?: $assistantText;

                    $usagePrompt = data_get($json, 'usage.prompt_tokens');
                    $usageCompletion = data_get($json, 'usage.completion_tokens');
                    $estimatedPromptTokens = $usagePrompt ?? $estimatedPromptTokens;
                    $estimatedCompletionTokens = $usageCompletion ?? $this->estimateTokens($assistantText);
                    $tr = new TherapistRecommendationService;
                    $cr = new ContentRecommendationService;
                    $recommendations['therapists'] = $tr->recommendForUser($user, 3)->map(function ($tp) {
                        return [
                            'therapist_user_id' => $tp->user_id,
                            'specializations' => $tp->specializations,
                            'languages' => $tp->languages,
                            'rating_average' => $tp->rating_average,
                            'match_score' => $tp->match_score,
                        ];
                    })->all();
                    $recommendations['articles'] = $cr->recommendForUser($user, 5)->map(function ($a) {
                        return [
                            'title' => $a->title,
                            'slug' => $a->slug,
                            'summary' => $a->summary,
                            'tags' => $a->tags,
                            'match_score' => $a->match_score,
                        ];
                    })->all();
                } else {
                    Log::warning('AI Companion: All providers unavailable or returned no response');
                }
            } catch (\Throwable $e) {
                Log::error('AI Companion: Unexpected error in chat', ['error' => $e->getMessage()]);
            }
        }

        if (! ($bypassOnRisk && $riskAnalysis['requires_escalation'])) {
            $lower = mb_strtolower($message);

            // Comprehensive risk check across all supported languages
            $isHighRisk = $riskService->containsHighRiskKeywords($lower, $langPref);
            $isAbuse = $riskService->containsAbuseKeywords($lower, $langPref);

            if ($isHighRisk || $isAbuse) {
                $crisis = true;
                $riskLevel = $isHighRisk ? 'critical' : 'high';

                // Dispatch event for Clinical Advisors
                event(new CompanionRiskEscalationEvent($user, $message, $riskLevel, $sessionId));

                Log::warning('AI Companion: Crisis detected and escalated', [
                    'user_id' => $user->id,
                    'risk_level' => $riskLevel,
                    'language' => $langPref,
                ]);
            }
        }

        $cost = $this->estimateCost($estimatedPromptTokens, $estimatedCompletionTokens);

        $sanitized = $this->stripDebugTags($assistantText);
        $aiChatRecord = AIChat::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'message' => $sanitized,
            'sender' => 'ai',
            'risk_level' => $crisis ? ($riskLevel ?: 'high') : null,
            'metadata' => [
                'source' => 'companion',
                'usage' => [
                    'prompt_tokens' => $estimatedPromptTokens,
                    'completion_tokens' => $estimatedCompletionTokens,
                    'cost' => $cost,
                ],
                'risk' => $riskAnalysis,
                'recommendations' => $recommendations,
            ],
        ]);

        // Summarisation trigger: every 10 messages, generate and store a cross-session summary.
        // This is the core of Doctor Onwynd's memory (Section 9.2).
        $this->maybeSummariseConversation($user, $sessionId);

        return [
            'conversation_id' => $sessionId,
            'ai_chat_id' => $aiChatRecord->id,
            'message' => $sanitized,
            'contains_crisis_keywords' => $crisis,
            'risk' => $riskAnalysis,
            'usage' => [
                'prompt_tokens' => $estimatedPromptTokens,
                'completion_tokens' => $estimatedCompletionTokens,
                'cost' => $cost,
            ],
            'recommendations' => $recommendations,
        ];
    }

    /**
     * After every 10 messages in a session, generate a plain-text summary and persist it.
     *
     * The summary is intentionally terse — it captures themes, stated concerns, and
     * progress so the next session system prompt can reference them without exposing
     * full transcripts (privacy boundary).
     */
    private function maybeSummariseConversation(User $user, string $sessionId): void
    {
        try {
            $messages = AIChat::where('user_id', $user->id)
                ->where('session_id', $sessionId)
                ->orderBy('id')
                ->get(['id', 'message', 'sender']);

            $count = $messages->count();

            // Only summarise at exact multiples of 10 (10, 20, 30…)
            if ($count === 0 || $count % 10 !== 0) {
                return;
            }

            // Check we haven't already summarised this window
            $lastSummary = AiConversationSummary::where('user_id', $user->id)
                ->where('session_id', $sessionId)
                ->orderBy('id', 'desc')
                ->first();

            $alreadySummarisedUpTo = $lastSummary?->last_message_id ?? 0;
            $latestMessageId = $messages->last()->id;

            if ($alreadySummarisedUpTo >= $latestMessageId) {
                return; // Already summarised this window
            }

            // Build a minimal transcript of the last 10 messages for the summary prompt
            $window = $messages->slice(-10)->values();
            $transcript = $window->map(fn ($m) => ucfirst($m->sender).': '.$m->message)->implode("\n");

            $summaryPrompt = 'Summarise this mental health support conversation in 2-3 sentences. '
                .'Focus on: what the user is going through, any progress made, and key themes. '
                ."Be factual and brief. Do not include names or identifying details.\n\n"
                .$transcript;

            $summaryPayload = [
                'model' => config('services.groq.model', 'llama-3.3-70b-versatile'),
                'messages' => [
                    ['role' => 'system', 'content' => 'You summarise mental health conversations factually and briefly.'],
                    ['role' => 'user', 'content' => $summaryPrompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 150,
            ];

            $json = $this->callWithFallback($summaryPayload);
            $summary = data_get($json, 'choices.0.message.content');

            if ($summary) {
                AiConversationSummary::create([
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                    'summary' => trim($summary),
                    'message_count' => 10,
                    'last_message_id' => $latestMessageId,
                ]);
            }
        } catch (\Throwable $e) {
            // Summarisation failure must never break the chat response
            Log::warning('AI conversation summary generation failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Try AI providers in order: Groq → OpenAI → Ollama.
     * Returns the decoded JSON response array, or null if all fail.
     */
    private function callWithFallback(array $payload): ?array
    {
        // 1. Groq (primary)
        $groqKey = config('services.groq.api_key');
        if ($groqKey) {
            try {
                $p = array_merge($payload, ['model' => config('services.groq.model', 'llama-3.3-70b-versatile')]);
                $response = Http::timeout(30)
                    ->withHeaders(['Authorization' => 'Bearer '.$groqKey])
                    ->post('https://api.groq.com/openai/v1/chat/completions', $p);
                if ($response->successful()) {
                    return $response->json();
                }
                Log::warning('AI Companion: Groq returned '.$response->status().', trying OpenAI');
            } catch (\Throwable $e) {
                Log::warning('AI Companion: Groq exception, trying OpenAI', ['error' => $e->getMessage()]);
            }
        }

        // 2. OpenAI (secondary)
        $openaiKey = config('services.openai.api_key');
        if ($openaiKey && ! str_starts_with($openaiKey, 'your_')) {
            try {
                $p = array_merge($payload, ['model' => config('services.openai.model', 'gpt-4o-mini')]);
                $response = Http::timeout(30)
                    ->withHeaders(['Authorization' => 'Bearer '.$openaiKey])
                    ->post('https://api.openai.com/v1/chat/completions', $p);
                if ($response->successful()) {
                    return $response->json();
                }
                Log::warning('AI Companion: OpenAI returned '.$response->status().', trying Ollama');
            } catch (\Throwable $e) {
                Log::warning('AI Companion: OpenAI exception, trying Ollama', ['error' => $e->getMessage()]);
            }
        }

        // 3. Ollama / Phi-3.5 (offline tertiary — OpenAI-compatible endpoint)
        $ollamaBase = rtrim((string) config('services.ollama.base_url', 'http://127.0.0.1:11434'), '/');
        try {
            $p = array_merge($payload, ['model' => config('services.ollama.model', 'phi3.5')]);
            $response = Http::timeout(60)->post($ollamaBase.'/v1/chat/completions', $p);
            if ($response->successful()) {
                return $response->json();
            }
            Log::error('AI Companion: Ollama returned '.$response->status().'. All providers exhausted.');
        } catch (\Throwable $e) {
            Log::error('AI Companion: Ollama exception. All providers exhausted.', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function buildEmergencyMessageForUser(User $user): string
    {
        $country = (string) ($user->country_code ?? $user->country ?? $user->locale ?? app()->getLocale() ?? '');
        $country = strtoupper(substr($country, 0, 2));
        $map = config('emergency.emergency_numbers', []);
        $default = config('emergency.default_number', '112');
        $number = $map[$country] ?? $default;

        return "I am detecting serious safety concerns. If you are in immediate danger or thinking about harming yourself, contact local emergency services now. In {$country}, dial {$number}. You can also reach your local crisis line or talk to someone you trust. I can provide resources and stay with you here.";
    }

    public function getConversation(User $user, string $conversationId): array
    {
        $messages = AIChat::where('user_id', $user->id)
            ->where('session_id', $conversationId)
            ->orderBy('id')
            ->get(['message', 'sender', 'created_at']);

        return [
            'conversation_id' => $conversationId,
            'messages' => $messages->map(fn ($m) => [
                'sender' => $m->sender,
                'message' => $m->message,
                'created_at' => $m->created_at,
            ]),
        ];
    }

    public function deleteConversation(User $user, string $conversationId): bool
    {
        $count = AIChat::where('user_id', $user->id)
            ->where('session_id', $conversationId)
            ->delete();

        return $count > 0;
    }

    public function getConversations(User $user): array
    {
        $sessions = AIChat::where('user_id', $user->id)
            ->select('session_id')
            ->distinct()
            ->get()
            ->pluck('session_id');

        $result = [];
        foreach ($sessions as $sid) {
            $messages = AIChat::where('user_id', $user->id)
                ->where('session_id', $sid)
                ->orderBy('id', 'desc')
                ->get(['message', 'sender', 'created_at']);

            $last = $messages->first();
            $result[] = [
                'id' => $sid,
                'conversation_id' => $sid,
                'title' => $last ? (strlen($last->message) > 50 ? substr($last->message, 0, 47).'...' : $last->message) : 'Conversation',
                'started_at' => optional($messages->last())->created_at,
                'ended_at' => optional($last)->created_at,
                'total_messages' => $messages->count(),
            ];
        }

        return $result;
    }

    private function estimateTokens(string $text): int
    {
        $len = max(1, strlen($text));

        return (int) ceil($len / 4); // rough char->token estimate
    }

    private function estimateCost(int $promptTokens, int $completionTokens): float
    {
        $driver = config('services.ai.default', 'openai');
        if ($driver === 'groq') {
            $pPer1k = (float) (config('services.groq.pricing.prompt_per_1k') ?? 0.0);
            $cPer1k = (float) (config('services.groq.pricing.completion_per_1k') ?? 0.0);
        } elseif ($driver === 'grok') {
            $pPer1k = (float) (config('services.grok.pricing.prompt_per_1k') ?? 0.0);
            $cPer1k = (float) (config('services.grok.pricing.completion_per_1k') ?? 0.0);
        } else {
            $pPer1k = (float) (config('services.openai.pricing.prompt_per_1k') ?? 0.3);
            $cPer1k = (float) (config('services.openai.pricing.completion_per_1k') ?? 0.6);
        }
        $cost = ($promptTokens / 1000.0) * $pPer1k + ($completionTokens / 1000.0) * $cPer1k;

        return round($cost, 4);
    }

    private function resolveLanguage(User $user, string $message, ?string $preferred): string
    {
        $map = [
            'en' => 'en',
            'ig' => 'ig',
            'yo' => 'yo',
            'ha' => 'ha',
            'sw' => 'sw',
            'tiv' => 'tiv',
            'pcm' => 'pcm',
        ];
        $candidate = $preferred ?: ($user->language ?? 'en');
        $candidate = strtolower((string) $candidate);
        if (isset($map[$candidate])) {
            return $map[$candidate];
        }
        $lower = mb_strtolower($message);
        if (preg_match('/\\b(kele|ụzọ|anyị|ị)\\b/u', $lower)) {
            return 'ig';
        }
        if (preg_match('/\\b(da|kai|yaya|ni|ka)\\b/u', $lower)) {
            return 'ha';
        }
        if (preg_match('/\\b(na|wewe|sisi|habari)\\b/u', $lower)) {
            return 'sw';
        }
        if (preg_match('/\\b(bawo|ṣé|ẹ|ni)\\b/u', $lower)) {
            return 'yo';
        }
        if (preg_match('/\\b(wan|mba|tiv)\\b/i', $lower)) {
            return 'tiv';
        }
        if (preg_match('/\\b(wetin|dey|no vex|abeg|make)\\b/i', $lower)) {
            return 'pcm';
        }

        return 'en';
    }

    private function languageHumanName(string $code): string
    {
        return match ($code) {
            'ig' => 'Igbo',
            'yo' => 'Yoruba',
            'ha' => 'Hausa',
            'sw' => 'Swahili',
            'tiv' => 'Tiv',
            'pcm' => 'Nigerian Pidgin',
            default => 'English',
        };
    }

    private function isIdentityQuestion(string $text): bool
    {
        $t = strtolower($text);

        return str_contains($t, 'your name') || str_contains($t, 'what is your name') || str_contains($t, 'who are you');
    }

    private function identityReply(string $firstName, string $lang): string
    {
        return match ($lang) {
            'ig' => "Ndewo {$firstName}. Aha m bụ Onwynd AI Companion. A bụ m onye ọgụgụ isi dijitalụ nke na-ege gị ntị ma na-enye nkwado na ndụmọdụ dị nro, nke dabere na ọmụmụ ihe. Echegbula—mkparịta ụka anyị zoro ezo. Kedu ka ị na-eche ugbu a?",
            'yo' => "Ẹ káàsán {$firstName}. Orúkọ mi ni Onwynd AI Companion. Ẹrọ ọlọ́gbọ́n ni mi tí ń gbọ́ ọ́ àti kí n fún ọ ní ìmọ̀ràn tó ní ìbànújẹ rere, tó dá lórí ìmọ̀ ìjìnlẹ̀. Àjùmóṣe wa jẹ́ ìpamọ́. Báwo ni ìmọ̀lára rẹ báyìí?",
            'ha' => "Sannu {$firstName}. Sunana Onwynd AI Companion. Ni manhaja mai hankali da ke sauraron ka kuma na ba da goyon baya da shawarwari masu tausayi bisa hujjoji. Zancenmu na sirri ne. Yaya kake ji yanzu?",
            'sw' => "Hujambo {$firstName}. Jina langu ni Onwynd AI Companion. Mimi ni msaidizi wa dijitali anayekusikiliza na kukupa ushauri wa huruma unaotegemea ushahidi. Mazungumzo yetu ni ya faragha. Unajisikia vipi sasa?",
            'tiv' => "Mnger {$firstName}. Mkem we Onwynd AI Companion. Nyian iyol u we u sha yôô u yange u or u ngu sha u tar u nenge u or sha ya. Mluan sha nger a lu u fan. U sha vihi ga?",
            'pcm' => "Hello {$firstName}. My name na Onwynd AI Companion. I be digital helper wey dey listen to you and give gentle, evidence-based support. Our talk dey private. How you dey feel now?",
            default => "Hey {$firstName}. I'm the Onwynd AI Companion — a supportive, private, AI assistant that listens and offers gentle, evidence-based suggestions. Our chat is confidential. How are you feeling right now?",
        };
    }

    private function stripDebugTags(string $text): string
    {
        $result = $text;
        $markers = ['[ED:'];
        foreach ($markers as $marker) {
            while (($idx = strrpos($result, $marker)) !== false) {
                $jsonStart = $idx + strlen($marker);
                $depth = 0;
                $jsonEnd = -1;
                for ($i = $jsonStart; $i < strlen($result); $i++) {
                    $ch = $result[$i];
                    if ($ch === '{' || $ch === '[') {
                        $depth++;
                    } elseif ($ch === '}' || $ch === ']') {
                        $depth--;
                        if ($depth === 0) {
                            $jsonEnd = $i + 1;
                            break;
                        }
                    }
                }
                if ($jsonEnd === -1) {
                    $after = substr($result, $jsonStart);
                    $closeIdx = strpos($after, ']');
                    $tagEnd = $closeIdx !== false ? $jsonStart + $closeIdx + 1 : $jsonStart;
                    $result = trim(substr($result, 0, $idx).substr($result, $tagEnd));
                } else {
                    $afterJson = substr($result, $jsonEnd);
                    $closeIdx = strpos($afterJson, ']');
                    if ($closeIdx === false) {
                        $result = trim(substr($result, 0, $idx).substr($result, $jsonEnd));
                    } else {
                        $tagEnd = $jsonEnd + $closeIdx + 1;
                        $result = trim(substr($result, 0, $idx).substr($result, $tagEnd));
                    }
                }
            }
        }
        $marker = '[NOTED:';
        while (($idx = strrpos($result, $marker)) !== false) {
            $jsonStart = $idx + strlen($marker);
            $depth = 0;
            $jsonEnd = -1;
            for ($i = $jsonStart; $i < strlen($result); $i++) {
                $ch = $result[$i];
                if ($ch === '{' || $ch === '[') {
                    $depth++;
                } elseif ($ch === '}' || $ch === ']') {
                    $depth--;
                    if ($depth === 0) {
                        $jsonEnd = $i + 1;
                        break;
                    }
                }
            }
            if ($jsonEnd === -1) {
                break;
            }
            $afterJson = substr($result, $jsonEnd);
            $closeIdx = strpos($afterJson, ']');
            if ($closeIdx === false) {
                break;
            }
            $tagEnd = $jsonEnd + $closeIdx + 1;
            $result = trim(substr($result, 0, $idx).substr($result, $tagEnd));
        }

        // Strip [THERAPIST_RECOMMEND:{...}] tags — parsed by frontend, must not persist to DB
        $result = preg_replace('/\[THERAPIST_RECOMMEND:\{[^}]*\}\]/', '', $result);
        $result = trim($result);

        // Fix common name misspellings and word breaks
        $result = $this->sanitizeAIResponse($result);

        return $result;
    }

    /**
     * Sanitize AI response to fix word breaks, common misspellings, and name issues
     */
    private function sanitizeAIResponse(string $content): string
    {
        if (! $content) {
            return $content;
        }
        $original = $content;

        // Extract and preserve markdown links to prevent breaking them
        $links = [];
        $linkCounter = 0;

        // Find and temporarily replace markdown links with placeholders
        $content = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($matches) use (&$links, &$linkCounter) {
            $placeholder = '___LINK_PLACEHOLDER_'.$linkCounter.'___';
            $links[$placeholder] = $matches[0];
            $linkCounter++;

            return $placeholder;
        }, $content);

        // Fix word breaks (words split across lines) - but not in links
        $content = preg_replace('/(\w+)-\s*\n\s*(\w+)/', '$1-$2', $content);

        // Fix common misspellings
        $commonMisspellings = [
            'recieve' => 'receive',
            'seperate' => 'separate',
            'definately' => 'definitely',
            'occured' => 'occurred',
            'begining' => 'beginning',
            'acheive' => 'achieve',
            'neccessary' => 'necessary',
            'existance' => 'existence',
            'independant' => 'independent',
            'sucess' => 'success',
            'freind' => 'friend',
            'thier' => 'their',
            'untill' => 'until',
            'writting' => 'writing',
            'experiance' => 'experience',
            'maintainance' => 'maintenance',
            'recomend' => 'recommend',
            'calender' => 'calendar',
            'Febuary' => 'February',
            'Wensday' => 'Wednesday',
            'tommorrow' => 'tomorrow',
            'yesturday' => 'yesterday',
            'alot' => 'a lot',
            'alright' => 'all right',
        ];

        foreach ($commonMisspellings as $wrong => $correct) {
            $content = preg_replace('/\b'.preg_quote($wrong, '/').'\b/i', $correct, $content);
        }

        // Fix common Nigerian name misspellings
        $nigerianNameCorrections = [
            'Adeyemi' => 'Adeyemi',
            'Okafor' => 'Okafor',
            'Chukwu' => 'Chukwu',
            'Obi' => 'Obi',
            'Emeka' => 'Emeka',
            'Chinedu' => 'Chinedu',
            'Ifeanyi' => 'Ifeanyi',
            'Olumide' => 'Olumide',
            'Tunde' => 'Tunde',
            'Bola' => 'Bola',
            'Yemi' => 'Yemi',
            'Funmi' => 'Funmi',
            'Tolu' => 'Tolu',
            'Gbenga' => 'Gbenga',
            'Femi' => 'Femi',
            'Kemi' => 'Kemi',
            'Sola' => 'Sola',
            'Ngozi' => 'Ngozi',
            'Chioma' => 'Chioma',
            'Uche' => 'Uche',
        ];

        foreach ($nigerianNameCorrections as $correct => $correct) {
            // Fix misspellings of common Nigerian names
            $content = preg_replace('/\b'.preg_quote($correct, '/').'\b/i', $correct, $content);
        }

        // Ensure proper spacing after punctuation
        $content = preg_replace('/([.!?])(?![\s\/\]])([A-Za-z])/', '$1 $2', $content);

        // Fix multiple spaces
        $content = preg_replace('/[ \t]{2,}/', ' ', $content);

        // Restore markdown links
        foreach ($links as $placeholder => $originalLink) {
            $content = str_replace($placeholder, $originalLink, $content);
        }

        // Trim whitespace
        $result = trim($content);

        // Sanity check
        if (strlen($result) < 10 && strlen($original) >= 10) {
            return $original;
        }

        return $result;
    }

    /**
     * Generate CBT-based response templates for common mental health scenarios
     */
    private function generateCBTResponse(string $message, string $firstName): array
    {
        $lowerMessage = strtolower($message);
        $responses = [];

        // Thought challenging for negative self-talk
        if (preg_match('/\b(stupid|worthless|failure|hopeless|useless)\b/i', $message)) {
            $responses[] = "I hear that you're feeling really down on yourself, {$firstName}. Those are heavy words to carry. Let's look at this together - what evidence do you have that contradicts this thought? What would you say to a good friend who felt this way?";
            $responses[] = "Those thoughts can feel so convincing when we're struggling, {$firstName}. But thoughts aren't facts. What are some things you've accomplished recently, even small ones? Sometimes our harshest critic lives in our own mind.";
        }

        // Behavioral activation for low motivation
        if (preg_match('/\b(can\'t get out of bed|no motivation|stuck|nothing matters)\b/i', $message)) {
            $responses[] = "When everything feels overwhelming, {$firstName}, starting small can make a big difference. What's one tiny thing you could do right now - maybe just sit up, drink some water, or open a window? Small steps count.";
            $responses[] = "I understand that heavy feeling, {$firstName}. Sometimes our brain needs help getting unstuck. Would you be open to trying a 2-minute activity? Maybe step outside for fresh air or listen to one uplifting song?";
        }

        // Anxiety and worry patterns
        if (preg_match('/\b(worried|anxious|panic|scared|what if)\b/i', $message)) {
            $responses[] = "Anxiety can make our minds race to worst-case scenarios, {$firstName}. Let's ground ourselves in the present. Can you name 5 things you can see right now? 4 things you can touch? This helps anchor us when worry takes over.";
            $responses[] = "Those 'what if' thoughts can spiral quickly, {$firstName}. Try this: write down your worry, then ask yourself - is this happening right now? If not, gently bring your attention back to this moment. You're safe right now.";
        }

        // Sleep issues
        if (preg_match('/\b(can\'t sleep|insomnia|tossing and turning|racing thoughts)\b/i', $message)) {
            $responses[] = "Sleep struggles are so frustrating, {$firstName}. Our brains sometimes need help winding down. Try this: tense and release each muscle group starting from your toes. Or imagine a peaceful place in detail - what would you see, hear, smell there?";
            $responses[] = "When sleep won't come, {$firstName}, it's tempting to fight it. But what if we worked with it instead? Keep your eyes open and try to stay awake - sometimes reverse psychology helps. If not, gentle acceptance can ease the struggle.";
        }

        // Relationship stress
        if (preg_match('/\b(alone|lonely|no one understands|isolated|rejected)\b/i', $message)) {
            $responses[] = "Feeling disconnected hurts deeply, {$firstName}. Humans are wired for connection. Even though it feels like you're alone, reaching out to just one person - even for a brief chat - can start to shift this feeling. What small connection could you make today?";
            $responses[] = "That isolation can feel so heavy, {$firstName}. Sometimes our thoughts tell us we're alone when support is actually available. Can you think of one person who might understand, even a little? Or would you like to explore our [Community Directory](/community) to find others who get it?";
        }

        return $responses;
    }

    /**
     * Generate NLP-based reframing suggestions
     */
    private function generateNLPReframe(string $message, string $firstName): array
    {
        $responses = [];

        // Reframe absolute language
        if (preg_match('/\b(always|never|everyone|no one|everything|nothing)\b/i', $message)) {
            $responses[] = "I notice some absolute words there, {$firstName}. When we say 'always' or 'never,' our brain tends to believe it. What would happen if we softened that to 'sometimes' or 'often'? How might that change how you feel about it?";
            $responses[] = "Those absolute words can trap us, {$firstName}. 'Always' and 'never' rarely tell the whole story. Can you think of one exception to that statement? Even one counter-example can start to loosen its grip on us.";
        }

        // Mind reading assumptions
        if (preg_match('/\b(they think|people say|everyone believes|no one cares)\b/i', $message)) {
            $responses[] = "It sounds like you're mind-reading, {$firstName} - assuming you know what others think. But we can't really know their thoughts. What evidence do you actually have for what they're thinking? What might be some other possibilities?";
            $responses[] = "When we assume we know others' thoughts, {$firstName}, we often imagine the worst. But people are usually more focused on their own concerns. What would you say to a friend who made this same assumption about others?";
        }

        // Catastrophizing
        if (preg_match('/\b(disaster|terrible|awful|horrible|worst|ruined)\b/i', $message)) {
            $responses[] = "Those are some strong words, {$firstName}. When we catastrophize, we imagine the worst possible outcome. But most situations aren't truly disasters. On a scale of 1-10, how bad is this really? What would a 4 or 5 look like instead?";
            $responses[] = "I hear that this feels overwhelming, {$firstName}. But let's reality-check: will this still matter in a week? A month? A year? Sometimes zooming out helps us see that we're more resilient than we feel in the moment.";
        }

        return $responses;
    }
}
