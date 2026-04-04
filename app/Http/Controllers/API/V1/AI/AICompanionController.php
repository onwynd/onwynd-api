<?php

namespace App\Http\Controllers\API\V1\AI;

use App\Events\AI\CompanionRiskEscalationEvent;
use App\Http\Controllers\API\BaseController;
use App\Models\AIChat;
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

    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'conversation_id' => 'nullable|string',
        ]);

        $user = $request->user();
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
        $user = $request->user();
        $result = $this->service->getConversation($user, $conversationId);

        return $this->sendResponse($result, 'Conversation retrieved');
    }

    public function deleteConversation(Request $request, string $conversationId)
    {
        $user = $request->user();
        $deleted = $this->service->deleteConversation($user, $conversationId);

        if (! $deleted) {
            return $this->sendError('Conversation not found', [], 404);
        }

        return $this->sendResponse(['deleted' => true], 'Conversation deleted');
    }

    public function getConversations(Request $request)
    {
        $user = $request->user();
        $result = $this->service->getConversations($user);

        return $this->sendResponse($result, 'Conversations retrieved');
    }

    /**
     * Get companion personal notes for the current user.
     */
    public function getCompanionNotes(Request $request)
    {
        $user = $request->user();
        $prefs = $user->preferences ?? [];
        $notes = $prefs['companion_notes'] ?? [];

        return $this->sendResponse($notes, 'Companion notes retrieved');
    }

    /**
     * Update companion personal notes (hobbies, favorite foods, activities, etc.).
     * Called by the frontend after the AI mentions noting down something personal.
     */
    public function updateCompanionNotes(Request $request)
    {
        $request->validate([
            'hobbies' => 'sometimes|array',
            'hobbies.*' => 'string|max:100',
            'favorite_foods' => 'sometimes|array',
            'favorite_foods.*' => 'string|max:100',
            'activities' => 'sometimes|array',
            'activities.*' => 'string|max:100',
            'notes' => 'sometimes|array',
            'notes.*' => 'string|max:500',
            // life_situation: keyed object — each key replaces its stored value
            'life_situation' => 'sometimes|array',
            'life_situation.*' => 'nullable|string|max:300',
            // milestones: appended to history (capped at 20)
            'milestones' => 'sometimes|array',
            'milestones.*' => 'string|max:300',
            // personal_details: keyed object — birthday (MM-DD), hometown, nickname, etc.
            'personal_details' => 'sometimes|array',
            'personal_details.*' => 'nullable|string|max:100',
        ]);

        $user = $request->user();
        $prefs = $user->preferences ?? [];
        $existing = $prefs['companion_notes'] ?? [
            'hobbies' => [],
            'favorite_foods' => [],
            'activities' => [],
            'notes' => [],
            'life_situation' => [],
            'milestones' => [],
            'personal_details' => [],
        ];

        // Merge arrays without duplicates
        $merge = function (array $old, array $new): array {
            return array_values(array_unique(array_merge($old, $new)));
        };

        if ($request->has('hobbies')) {
            $existing['hobbies'] = $merge($existing['hobbies'] ?? [], $request->hobbies);
        }
        if ($request->has('favorite_foods')) {
            $existing['favorite_foods'] = $merge($existing['favorite_foods'] ?? [], $request->favorite_foods);
        }
        if ($request->has('activities')) {
            $existing['activities'] = $merge($existing['activities'] ?? [], $request->activities);
        }
        if ($request->has('notes')) {
            $existing['notes'] = $merge($existing['notes'] ?? [], $request->notes);
        }

        // life_situation: merge individual keys — each key REPLACES its old value
        if ($request->has('life_situation') && is_array($request->life_situation)) {
            $existingLife = is_array($existing['life_situation'] ?? null) ? $existing['life_situation'] : [];
            foreach ($request->life_situation as $key => $value) {
                // Whitelist safe key names (lowercase letters + underscore only)
                if (is_string($key) && preg_match('/^[a-z_]{1,30}$/', $key)) {
                    if (empty($value)) {
                        unset($existingLife[$key]); // empty value removes the key
                    } else {
                        $existingLife[$key] = mb_substr((string) $value, 0, 300);
                    }
                }
            }
            $existing['life_situation'] = $existingLife;
        }

        // milestones: append new entries (keep last 20)
        if ($request->has('milestones') && is_array($request->milestones)) {
            $existingMilestones = is_array($existing['milestones'] ?? null) ? $existing['milestones'] : [];
            foreach ($request->milestones as $milestone) {
                if (is_string($milestone) && ! empty(trim($milestone))) {
                    $existingMilestones[] = mb_substr(trim($milestone), 0, 300);
                }
            }
            // Keep last 20 milestones
            if (count($existingMilestones) > 20) {
                $existingMilestones = array_slice($existingMilestones, -20);
            }
            $existing['milestones'] = array_values($existingMilestones);
        }

        // personal_details: merge individual keys (birthday MM-DD, hometown, nickname, etc.)
        if ($request->has('personal_details') && is_array($request->personal_details)) {
            $existingPersonal = is_array($existing['personal_details'] ?? null) ? $existing['personal_details'] : [];
            foreach ($request->personal_details as $key => $value) {
                if (is_string($key) && preg_match('/^[a-z_]{1,30}$/', $key)) {
                    if (empty($value)) {
                        unset($existingPersonal[$key]); // empty value removes the key
                    } else {
                        $existingPersonal[$key] = mb_substr((string) $value, 0, 100);
                    }
                }
            }
            $existing['personal_details'] = $existingPersonal;
        }

        $existing['last_updated'] = now()->toISOString();

        $prefs['companion_notes'] = $existing;
        $user->update(['preferences' => $prefs]);

        return $this->sendResponse($existing, 'Companion notes updated');
    }

    /**
     * Return the current user's AI quota status.
     * Used by the frontend to display "X chats left" and any soft-nudge messages.
     */
    public function quotaStatus(Request $request)
    {
        $user = $request->user();
        $status = (new AiQuotaService)->getStatus($user);

        return $this->sendResponse($status, 'Quota status retrieved');
    }

    public function stream(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'conversation_id' => 'nullable|string',
        ]);

        $user = $request->user();
        $message = (string) $request->string('message');
        $conversationId = $request->string('conversation_id') ?: null;
        $sessionId = $conversationId ?: (string) Str::uuid();
        $preferredLanguage = (string) ($request->string('language') ?: ($user->language ?? 'en'));

        $risk = (new RiskDetectionService)->analyze($message);
        $quotaService = new AiQuotaService;

        // Set distress flag (applies extended quota to the NEXT request)
        if (in_array($risk['risk_level'], ['high', 'severe'])) {
            $quotaService->setDistressFlag($user);

            // 1C. Crisis event logging
            $crisisEvent = CrisisEvent::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'org_id' => $user->organization_membership?->organization_id ?? null,
                'session_id' => $sessionId,
                'risk_level' => $risk['risk_level'],
                'triggered_at' => now(),
                'resources_shown' => true,
                'banner_shown' => true, // Backend assumes frontend shows it based on is_distress flag
                'override_active' => true,
            ]);

            // 2C. Admin/clinical alert — email notification
            $timestamp = now()->toDateTimeString();
            $hashedSession = substr(hash('sha256', $sessionId), 0, 12);
            $subject = 'Crisis flag triggered — Onwynd platform';
            $body = "A user on the Onwynd platform triggered a crisis detection flag at {$timestamp}.\n".
                    "The user has been shown emergency resources.\n".
                    "Session ID: {$hashedSession} (hashed).\n".
                    'No further action is required unless escalated by the user.';

            $recipient = config('onwynd.crisis_email', 'clinical@onwynd.com');

            try {
                Mail::raw($body, function ($message) use ($recipient, $subject) {
                    $message->to($recipient)
                        ->subject($subject);
                });
            } catch (\Throwable $e) {
                // Log failure but don't crash the user experience
                \Illuminate\Support\Facades\Log::error('Failed to send crisis email', ['error' => $e->getMessage()]);
            }
        }

        $bypassOnRisk = (bool) config('services.ai.bypass_llm_on_risk', true);
        if ($bypassOnRisk && $risk['requires_escalation']) {
            AIChat::create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'message' => $message,
                'sender' => 'user',
                'metadata' => ['source' => 'companion'],
            ]);
            event(new CompanionRiskEscalationEvent($user->id, $sessionId, $message, $risk));
            $safety = $this->buildEmergencyMessageForUser($user);

            return response()->stream(function () use ($user, $sessionId, $safety, $risk) {
                echo "event: start\n";
                echo 'data: '.json_encode(['status' => 'starting', 'is_distress' => true])."\n\n";
                ob_flush();
                flush();
                echo 'data: '.str_replace(["\r", "\n"], [' ', ' '], $safety)."\n\n";
                ob_flush();
                flush();
                echo "event: end\n";
                echo 'data: '.json_encode(['status' => 'completed'])."\n\n";
                ob_flush();
                flush();
                AIChat::create([
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                    'message' => $safety,
                    'sender' => 'ai',
                    'risk_level' => $risk['risk_level'],
                    'metadata' => ['source' => 'companion', 'stream' => true, 'risk' => $risk],
                ]);
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        $driver = config('services.ai.default', 'openai');
        if ($driver === 'groq') {
            $apiKey = config('services.groq.api_key');
            $model = config('services.groq.model', 'llama-3.3-70b-versatile');
            $base = 'https://api.groq.com/openai/v1';
        } elseif ($driver === 'grok') {
            $apiKey = config('services.grok.api_key');
            $model = config('services.grok.model', 'grok-1');
            $base = 'https://api.x.ai/v1';
        } else {
            $apiKey = config('services.openai.api_key');
            $model = config('services.openai.model', 'gpt-4o-mini');
            $base = 'https://api.openai.com/v1';
        }

        // ── Build rich, personalized system prompt ────────────────────────
        $firstName = $user->first_name ?? ($user->name ? explode(' ', $user->name)[0] : 'there');

        // Detect if this is the very first message in this session
        $isFirstMessage = ! AIChat::where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->exists();

        // Fetch recent assessment results (last 3)
        $recentAssessments = UserAssessmentResult::where('user_id', $user->id)
            ->with('assessment:id,title,type')
            ->orderBy('completed_at', 'desc')
            ->take(3)
            ->get(['assessment_id', 'total_score', 'severity_level', 'interpretation', 'completed_at']);

        $assessmentContext = '';
        if ($recentAssessments->isNotEmpty()) {
            $lines = $recentAssessments->map(function ($r) {
                $title = optional($r->assessment)->title ?? 'Assessment';
                $date = $r->completed_at ? \Carbon\Carbon::parse($r->completed_at)->diffForHumans() : '';
                $level = $r->severity_level ? " ({$r->severity_level})" : '';

                return "- {$title}: score {$r->total_score}{$level} {$date}";
            })->implode("\n");
            $assessmentContext = "\n\nRecent Assessment Results:\n{$lines}";
        }

        // Fetch recent mood logs (last 7 days)
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
            $moodContext = "\n\nRecent mood scores (1–10): ".implode(', ', $recentMoods)
                ." | Average: {$avg} | Trend: {$trend}";
            if ($latest !== null && $latest <= 4) {
                $moodContext .= ' — the user may be struggling, offer extra compassion.';
            } elseif ($latest !== null && $avg >= 7) {
                $moodContext .= ' — the user is doing well, affirm their progress!';
            }
        }

        // User goals/preferences
        $goals = collect($user->mental_health_goals ?? [])->filter()->values()->all();
        $goalsContext = ! empty($goals) ? "\nUser's mental health goals: ".implode(', ', $goals).'.' : '';

        // Language preference
        $langPref = strtolower($preferredLanguage) ?: 'en';
        $humanLang = match ($langPref) {
            'ig' => 'Igbo',
            'yo' => 'Yoruba',
            'ha' => 'Hausa',
            'sw' => 'Swahili',
            'tiv' => 'Tiv',
            'pcm' => 'Nigerian Pidgin',
            default => 'English',
        };

        // Companion personal notes + life context previously noted
        $prefs = $user->preferences ?? [];
        $companionNotes = $prefs['companion_notes'] ?? [];

        // Birthday check — compare stored MM-DD against today in WAT (Africa/Lagos)
        $personalDetails = is_array($companionNotes['personal_details'] ?? null) ? $companionNotes['personal_details'] : [];
        $todayBirthday = false;
        if (! empty($personalDetails['birthday']) && preg_match('/^\d{2}-\d{2}$/', $personalDetails['birthday'])) {
            $today = now()->setTimezone('Africa/Lagos')->format('m-d');
            $todayBirthday = ($personalDetails['birthday'] === $today);
        }

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
            // Life situation context (employment, finances, health, relationships, etc.)
            $lifeSituation = is_array($companionNotes['life_situation'] ?? null) ? $companionNotes['life_situation'] : [];
            if (! empty($lifeSituation)) {
                $lifeDetails = [];
                foreach ($lifeSituation as $key => $value) {
                    if (! empty($value)) {
                        $lifeDetails[] = "{$key}: {$value}";
                    }
                }
                if (! empty($lifeDetails)) {
                    $parts[] = 'Current life context — '.implode(', ', $lifeDetails);
                }
            }
            // Recent life milestones / significant events (last 5)
            $milestones = is_array($companionNotes['milestones'] ?? null) ? $companionNotes['milestones'] : [];
            if (! empty($milestones)) {
                $recentMilestones = array_slice($milestones, -5);
                $parts[] = 'Recent milestones: '.implode('; ', $recentMilestones);
            }
            // Personal details (birthday, hometown, nickname)
            if (! empty($personalDetails)) {
                $detailParts = [];
                if (! empty($personalDetails['birthday'])) {
                    try {
                        $bdFormatted = \Carbon\Carbon::createFromFormat('m-d', $personalDetails['birthday'])->format('F j');
                    } catch (\Throwable) {
                        $bdFormatted = $personalDetails['birthday'];
                    }
                    $detailParts[] = "birthday: {$bdFormatted}";
                }
                foreach (['hometown', 'nickname'] as $detailKey) {
                    if (! empty($personalDetails[$detailKey])) {
                        $detailParts[] = "{$detailKey}: {$personalDetails[$detailKey]}";
                    }
                }
                if (! empty($detailParts)) {
                    $parts[] = 'Personal details — '.implode(', ', $detailParts);
                }
            }
            if (! empty($parts)) {
                $personalCtx = "\n\nWhat I already know about {$firstName}: ".implode('. ', $parts).'.';
            }
        }

        $system = <<<SYSTEM
You are Doctor Onwynd — a warm, empathetic, and genuinely caring mental-health support companion.
The user's name is {$firstName}. Always address them by name naturally (e.g., "Hey {$firstName}", "I hear you, {$firstName}", "You've got this, {$firstName}").

IDENTITY:
You are the Onwynd AI Companion — a supportive, private mental-health assistant built for Nigerians and Africans. You are not a replacement for a licensed therapist. If asked whether you are human, be honest: you are an AI. Do not claim to read files unless they are uploaded for analysis.

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

PERSONALITY & TONE:
- Be warm, human, and conversational — never clinical or robotic.
- When the user seems low, unmotivated, or stressed, use their name to comfort them (e.g., "{$firstName}, I want you to know you're doing better than you think.").
- Celebrate small wins. Acknowledge effort. Make the user feel seen and appreciated.
- Keep responses concise (2–4 sentences unless detail is needed), actionable, and encouraging.
- If the user expresses crisis indicators (self-harm, suicide), immediately provide compassionate emergency guidance. Reference: SURPIN Nigeria: 0800-4357-4673.

MEMORY & LIFE CONTEXT TRACKING:
- You have a persistent memory of {$firstName}. Always use this context to make responses feel deeply personal.
- Track ANY life details the user mentions: hobbies, food preferences, activities, life situation (financial, employment, health, relationships, education, living situation).
- When life situations CHANGE (e.g., they mentioned struggling financially before, now they have a job), celebrate with them enthusiastically: "{$firstName}, that's incredible — I remember how tough things were and this is a huge step forward!"
- When you note something new or a change, end your message with a [NOTED:{...}] tag on its own line. ONLY include keys with genuinely new/changed data.

FORMAT RULES — follow exactly or the tag will be silently ignored:
  * The tag MUST start with exactly "[NOTED:" — never "[NOT:", "[NOTED{", or any other abbreviation or variant.
  * Every JSON key AND every string value must be wrapped in "double quotes" with a colon separating them. WRONG: {"nicknamemy guy"} — CORRECT: {"nickname":"my guy"}
  * Close with exactly "]" immediately after the closing brace.

Examples (copy format exactly):
  - Nickname: [NOTED:{"personal_details":{"nickname":"Bosco"}}]
  - Birthday: [NOTED:{"personal_details":{"birthday":"06-15"}}]
  - Hometown + nickname: [NOTED:{"personal_details":{"hometown":"Lagos","nickname":"Jay"}}]
  - Personal interests: [NOTED:{"hobbies":["yoga"],"activities":["morning walks"]}]
  - Life context (each key REPLACES old value): [NOTED:{"life_situation":{"finances":"got a new job, doing well","employment":"software engineer at TechCorp"}}]
  - Milestones (APPENDED to history): [NOTED:{"milestones":["landed new job at TechCorp - this is a big win"]}]
  - Combined: [NOTED:{"life_situation":{"employment":"new job"},"milestones":["started new job"],"hobbies":["hiking"]}]
- life_situation keys: employment, finances, health, relationships, education, living (short descriptive strings)
- personal_details keys: birthday (MM-DD format ONLY, e.g. "06-15" for June 15), hometown, nickname
- If the user mentions their birthday or birth date, immediately capture it: [NOTED:{"personal_details":{"birthday":"MM-DD"}}]
- milestones: significant positive or challenging events worth remembering
- Keep the JSON minimal and accurate. Never include empty arrays or null values.

DOCUMENT & IMAGE ANALYSIS:
- When you receive content with [Document: filename] or [Image/Screenshot: filename] tags, you are reviewing a real document the user has shared.
- Always start your response by warmly acknowledging what was shared: "Thanks {$firstName}, I've gone through your [document name]..." or "I can see your [document type], let me walk you through it..."
- Provide clear, compassionate explanations: use simple language for medical reports, be encouraging for school results, be practical and supportive for financial/legal documents.
- Summarise the key findings, then offer emotional support and practical next steps.
- Always end with an open question connecting back to their wellbeing: "How are you feeling about all of this, {$firstName}?"

ASSESSMENT SUGGESTIONS:
- Pay close attention to the user's emotional tone and expressiveness in each message.
- If you detect signs of anxiety, depression, burnout, low motivation, sleep issues, or stress, proactively suggest a relevant Onwynd assessment they can take. Say something like: "{$firstName}, it sounds like you might benefit from taking our [Assessment Name] — it only takes a few minutes and could give you useful insights."
- Available assessments: PHQ-9 (depression), GAD-7 (anxiety), PSS (stress), ISI (insomnia/sleep), MBI (burnout), PCL-5 (trauma).

ONWYND ACTIVITY RECOMMENDATIONS:
- Recommend relevant Onwynd features using markdown links so the user can tap straight to them. Use this exact format: [Feature Name](/path)
- Available features and their paths:
  * [Journal](/dashboard/journal) — for reflection and processing thoughts
  * [Breathing Exercise](/unwind) — for anxiety and stress relief
  * [Mini Meditation](/unwind) — for calm and focus
  * [Unwind Hub](/unwind) — soundscapes and relaxation
  * [Gratitude Journal](/dashboard/journal) — to shift perspective when feeling low
  * [Sleep Tracker](/dashboard/sleep) — for sleep issues
  * [Exercise Library](/exercise) — for energy and movement
  * [Assessments](/dashboard/assessments) — PHQ-9, GAD-7, etc. for structured self-evaluation
  * [Communities](/communities) — peer support groups and group sessions (family, couples, therapeutic groups)
  * [Book a Therapist](/therapist-booking) — licensed one-on-one therapist sessions
- Recommend naturally: "If you want, {$firstName}, try our [Breathing Exercise](/unwind) — it's really helpful for moments like this."
- Keep recommendations short and natural; link the feature name only, not extra text.
- If they mention a hobby or activity (like hiking, yoga, reading), say how it connects to their wellbeing.

SCORE-BASED APPRAISAL:{$assessmentContext}{$moodContext}
- Use the above data to personalise your responses. If scores indicate low mood or high severity, be especially compassionate and validating.
- If scores have improved, celebrate the progress: "{$firstName}, your scores show real improvement — that's something to be proud of!"
- If scores are worrying, gently encourage them: "{$firstName}, I can see things have been tough. Let's work through this together."
{$personalCtx}

USER CONTEXT:{$goalsContext}

LANGUAGE:
Reply in {$humanLang}. Use complete, clear sentences. Avoid dropping words.

THERAPIST RECOMMENDATIONS:
When the user wants professional help, describes persistent struggles (prolonged anxiety, depression, trauma, relationship issues, grief, burnout), or asks for a therapist, include at the very end of your message:
[THERAPIST_RECOMMEND:{"specialization":"<most relevant e.g. anxiety, depression, trauma, relationships, grief>","language":"<user preferred language, default en>"}]
Only when genuinely appropriate — never for casual chat. Never mention the tag — the app displays real verified therapists automatically.

FIRST MESSAGE BEHAVIOUR:
SYSTEM;

        if ($isFirstMessage) {
            $system .= "\n- This is the very first message in this session. Start with a warm, personalised greeting using {$firstName}'s name. Then gently ask what they'd like to focus on today. You may offer 2–3 topic prompts naturally in your reply (e.g., 'Are you dealing with stress at work, sleep trouble, or just need someone to talk to?').";
        }

        if ($todayBirthday) {
            $system .= "\n\nBIRTHDAY: Today is {$firstName}'s birthday. Before anything else, open with a warm, heartfelt birthday greeting from Onwynd. Be genuinely celebratory — use their name, wish them a wonderful birthday, and note how meaningful it is that they are here taking care of themselves on this special day. Then continue the conversation naturally.";
        }

        // Persist the user's message immediately
        AIChat::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'message' => $message,
            'sender' => 'user',
            'metadata' => ['source' => 'companion'],
        ]);

        $identityQ = function (string $t): bool {
            $t = strtolower($t);

            return str_contains($t, 'your name') || str_contains($t, 'what is your name') || str_contains($t, 'who are you');
        };
        $identityReply = function (string $firstName) use ($langPref) {
            return match ($langPref) {
                'ig' => "Ndewo {$firstName}. Aha m bụ Onwynd AI Companion. A bụ m onye ọgụgụ isi dijitalụ nke na-ege gị ntị ma na-enye nkwado na ndụmọdụ dị nro, nke dabere na ọmụmụ ihe. Mkparịta ụka anyị zoro ezo. Kedu ka ị na-eche ugbu a?",
                'yo' => "Ẹ káàsán {$firstName}. Orúkọ mi ni Onwynd AI Companion. Ẹrọ ọlọ́gbọ́n ni mi tí ń gbọ́ ọ́ àti kí n fún ọ ní ìmọ̀ràn tí ó dá lórí ìmọ̀ ìjìnlẹ̀. Àjùmóṣe wa jẹ́ ìpamọ́. Báwo ni ìmọ̀lára rẹ báyìí?",
                'ha' => "Sannu {$firstName}. Sunana Onwynd AI Companion. Ni manhaja mai hankali da ke sauraron ka kuma na ba da goyon baya da shawarwari masu tausayi bisa hujjoji. Zancenmu na sirri ne. Yaya kake ji yanzu?",
                'sw' => "Hujambo {$firstName}. Jina langu ni Onwynd AI Companion. Mimi ni msaidizi wa dijitali anayekusikiliza na kukupa ushauri wa huruma unaotegemea ushahidi. Mazungumzo yetu ni ya faragha. Unajisikia vipi sasa?",
                'tiv' => "Mnger {$firstName}. Mkem we Onwynd AI Companion. Nyian iyol u we u sha yôô u yange u or u ngu sha u tar u nenge u or sha ya. Mluan sha nger a lu u fan. U sha vihi ga?",
                'pcm' => "Hello {$firstName}. My name na Onwynd AI Companion. I be digital helper wey dey listen to you and give gentle, evidence-based support. Our talk dey private. How you dey feel now?",
                default => "Hey {$firstName}. I'm the Onwynd AI Companion — a supportive, private, AI assistant that listens and offers gentle, evidence-based suggestions. Our chat is confidential. How are you feeling right now?",
            };
        };
        if ($identityQ($message)) {
            $reply = $identityReply($firstName);

            return response()->stream(function () use ($user, $sessionId, $reply) {
                echo "event: start\n";
                echo 'data: '.json_encode(['status' => 'starting'])."\n\n";
                ob_flush();
                flush();
                echo 'data: '.str_replace(["\r", "\n"], [' ', ' '], $reply)."\n\n";
                ob_flush();
                flush();
                echo "event: end\n";
                echo 'data: '.json_encode(['status' => 'completed'])."\n\n";
                ob_flush();
                flush();
                $clean = preg_replace('/\\[ED:.*?\\]/s', '', $reply);
                $clean = preg_replace('/\[NOT(?:ED)?[:{](?:[^\[\]]*|\[[^\]]*\])*\]/s', '', $clean);
                AIChat::create([
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                    'message' => $clean,
                    'sender' => 'ai',
                    'metadata' => ['source' => 'companion', 'stream' => true],
                ]);
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        }
        // Fetch recent conversation history so the AI has context
        $historyMessages = AIChat::where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->orderBy('created_at', 'asc')
            ->limit(20)
            ->get(['sender', 'message'])
            ->map(fn ($m) => [
                'role' => $m->sender === 'ai' ? 'assistant' : 'user',
                'content' => $m->message,
            ])
            ->toArray();

        $payload = [
            'model' => $model,
            'stream' => true,
            'messages' => array_merge(
                [['role' => 'system', 'content' => $system]],
                $historyMessages,
                [['role' => 'user', 'content' => $message]],
            ),
            'temperature' => 0.7,
        ];

        $client = new Client([
            'headers' => [
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);

        return response()->stream(function () use ($client, $base, $payload, $user, $sessionId, $risk) {
            $assistantText = '';
            $isDistress = in_array($risk['risk_level'] ?? 'none', ['high', 'severe']);
            echo "event: start\n";
            echo 'data: '.json_encode(['status' => 'starting', 'is_distress' => $isDistress])."\n\n";
            ob_flush();
            flush();
            try {
                $response = $client->request('POST', $base.'/chat/completions', [
                    'json' => $payload,
                    'stream' => true,
                ]);

                $body = $response->getBody();
                while (! $body->eof()) {
                    $chunk = $body->read(2048);
                    if ($chunk === '') {
                        usleep(50000);

                        continue;
                    }
                    $lines = preg_split('/\r?\n/', $chunk);
                    foreach ($lines as $line) {
                        if (str_starts_with($line, 'data:')) {
                            $json = trim(substr($line, 5));
                            if ($json === '[DONE]') {
                                continue;
                            }
                            $data = json_decode($json, true);
                            $delta = data_get($data, 'choices.0.delta.content');
                            if ($delta) {
                                $assistantText .= $delta;
                                echo 'data: '.str_replace(["\r", "\n"], [' ', ' '], $delta)."\n\n";
                                ob_flush();
                                flush();
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                echo "event: error\n";
                echo 'data: '.json_encode(['message' => 'stream_failed'])."\n\n";
                ob_flush();
                flush();
            }

            echo "event: end\n";
            echo 'data: '.json_encode(['status' => 'completed'])."\n\n";
            ob_flush();
            flush();

            // Persist the assistant's message once the stream finishes
            if ($assistantText !== '') {
                $clean = preg_replace('/\\[ED:.*?\\]/s', '', $assistantText);
                $clean = preg_replace('/\[NOT(?:ED)?[:{](?:[^\[\]]*|\[[^\]]*\])*\]/s', '', $clean);
                $chatRecord = AIChat::create([
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                    'message' => trim($clean),
                    'sender' => 'ai',
                    'metadata' => ['source' => 'companion', 'stream' => true],
                ]);
                // Emit ai_chat_id so the frontend can attach feedback buttons
                echo "event: complete\n";
                echo 'data: '.json_encode(['ai_chat_id' => $chatRecord->id])."\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function buildEmergencyMessageForUser($user): string
    {
        $country = (string) ($user->country_code ?? $user->country ?? $user->locale ?? app()->getLocale() ?? '');
        $country = strtoupper(substr($country, 0, 2));
        $map = config('emergency.emergency_numbers', []);
        $default = config('emergency.default_number', '112');
        $number = $map[$country] ?? $default;

        return "I am detecting serious safety concerns. If you are in immediate danger or thinking about harming yourself, contact local emergency services now. In {$country}, dial {$number}. You can also reach your local crisis line or talk to someone you trust. I can provide resources and stay with you here.";
    }

    /**
     * Record thumbs-up / thumbs-down feedback on a single AI message.
     * Updates sentiment_score (1 = up, -1 = down) on the AIChat row.
     */
    public function feedback(Request $request, int $chatId)
    {
        $request->validate([
            'vote' => 'required|in:up,down',
        ]);

        $user = $request->user();
        $chat = AIChat::where('id', $chatId)
            ->where('user_id', $user->id)
            ->where('sender', 'ai')
            ->firstOrFail();

        $score = $request->input('vote') === 'up' ? 1 : -1;
        $meta  = $chat->metadata ?? [];
        $meta['user_feedback'] = $request->input('vote');

        $chat->update([
            'sentiment_score' => $score,
            'metadata'        => $meta,
        ]);

        return $this->sendResponse(['vote' => $request->input('vote')], 'Feedback recorded');
    }
}
