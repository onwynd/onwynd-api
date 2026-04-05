<?php

namespace App\Services\AI;

use App\Models\AdminAIChat;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminAIService
{
    /**
     * Memory update tag pattern.
     * The AI embeds [ADMIN_MEMO:{...json...}] in responses to update persistent memory.
     */
    private const MEMO_TAG_PATTERN = '/\[ADMIN_MEMO:([\s\S]*?)\]/';

    /**
     * Send a message to the admin AI, persist the exchange, and return the clean reply.
     *
     * @param  User  $admin  The authenticated admin user.
     * @param  string  $message  The admin's current message.
     * @param  string|null  $conversationId  UUID grouping this thread. Auto-generated if null.
     * @param  array|null  $fileContent  Optional file content array with 'content' and 'type' keys.
     * @param  array|null  $fileMetadata  Optional file metadata array with 'name' key.
     * @return array{reply: string, conversation_id: string}
     */
    public function chat(User $admin, string $message, ?string $conversationId = null, ?array $fileContent = null, ?array $fileMetadata = null, ?string $context = null): array
    {
        $conversationId = $conversationId ?: (string) Str::uuid();

        // Build enhanced message with file content
        $enhancedMessage = $message;
        if ($fileContent && isset($fileContent['content'])) {
            $enhancedMessage .= "\n\n[File Analysis]\n";
            $enhancedMessage .= 'File: '.($fileMetadata['name'] ?? 'Unknown')."\n";
            $enhancedMessage .= 'Type: '.($fileContent['type'] ?? 'Unknown')."\n";
            $enhancedMessage .= 'Content: '.$fileContent['content'];
        }

        // â”€â”€ 1. Persist the user's message immediately â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        AdminAIChat::create([
            'user_id' => $admin->id,
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => $enhancedMessage,
        ]);

        // â”€â”€ 2. Load conversation history (last 15 turns) from DB â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $history = AdminAIChat::where('user_id', $admin->id)
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->get(['role', 'content'])
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        // â”€â”€ 3. Build the AI payload â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $driver = config('services.ai.default', 'openai');
        [$apiKey, $model, $base] = $this->resolveDriver($driver);

        $systemPrompt = $this->buildSystemPrompt($admin, $conversationId);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        if ($context) {
            $messages[] = ['role' => 'system', 'content' => $this->buildContextPrompt($context)];
        }

        $messages = array_merge($messages, $history);

        // â”€â”€ 4. Call the AI â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $rawReply = $this->callAI($apiKey, $base, $model, $messages);

        // â”€â”€ 5. Extract memory tags and update admin's memory â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $cleanReply = $this->processMemoryTags($admin, $rawReply);

        // â”€â”€ 6. Persist the assistant reply â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        AdminAIChat::create([
            'user_id' => $admin->id,
            'conversation_id' => $conversationId,
            'role' => 'assistant',
            'content' => $cleanReply,
        ]);

        return [
            'reply' => $cleanReply,
            'conversation_id' => $conversationId,
        ];
    }

    /**
     * Return all conversation threads for an admin (latest message per thread).
     */
    public function getConversations(User $admin): array
    {
        return AdminAIChat::where('user_id', $admin->id)
            ->select('conversation_id', DB::raw('MAX(created_at) as last_at'), DB::raw('MIN(content) as preview'))
            ->where('role', 'user')
            ->groupBy('conversation_id')
            ->orderByDesc('last_at')
            ->limit(20)
            ->get()
            ->toArray();
    }

    /**
     * Return all messages in a specific conversation thread.
     */
    public function getConversation(User $admin, string $conversationId): array
    {
        return AdminAIChat::where('user_id', $admin->id)
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->get(['role', 'content', 'created_at'])
            ->toArray();
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // PRIVATE HELPERS
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    private function resolveDriver(string $driver): array
    {
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

    private function callAI(string $apiKey, string $base, string $model, array $messages): string
    {
        $client = new Client([
            'headers' => [
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 90,
            'verify' => config('app.env') === 'production', // Disable SSL verification for local development
        ]);

        $response = $client->post($base.'/chat/completions', [
            'json' => [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.45,
                'max_tokens' => 2000,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['choices'][0]['message']['content'] ?? 'Unable to generate a response. Please try again.';
    }

    /**
     * Parse [ADMIN_MEMO:{...}] tags out of the AI reply and update the admin's memory.
     * Returns the clean reply (tags stripped).
     */
    private function processMemoryTags(User $admin, string $rawReply): string
    {
        $prefs = $admin->preferences ?? [];
        $memory = $prefs['admin_ai_memory'] ?? $this->defaultMemory();
        $updated = false;

        $clean = preg_replace_callback(self::MEMO_TAG_PATTERN, function ($matches) use (&$memory, &$updated) {
            $json = @json_decode(trim($matches[1]), true);
            if (! is_array($json)) {
                return ''; // malformed tag â€” strip it
            }

            // focus_areas: merge without duplicates (cap 15)
            if (! empty($json['focus_areas']) && is_array($json['focus_areas'])) {
                $memory['focus_areas'] = array_values(
                    array_unique(array_merge($memory['focus_areas'] ?? [], $json['focus_areas']))
                );
                if (count($memory['focus_areas']) > 15) {
                    $memory['focus_areas'] = array_slice($memory['focus_areas'], -15);
                }
                $updated = true;
            }

            // noted_patterns: append (cap 25)
            if (! empty($json['noted_patterns']) && is_array($json['noted_patterns'])) {
                $existing = $memory['noted_patterns'] ?? [];
                foreach ($json['noted_patterns'] as $note) {
                    if (is_string($note) && ! in_array($note, $existing)) {
                        $existing[] = mb_substr(trim($note), 0, 400);
                    }
                }
                $memory['noted_patterns'] = array_values(array_slice($existing, -25));
                $updated = true;
            }

            // strategic_notes: append (cap 20)
            if (! empty($json['strategic_notes']) && is_array($json['strategic_notes'])) {
                $existing = $memory['strategic_notes'] ?? [];
                foreach ($json['strategic_notes'] as $note) {
                    if (is_string($note)) {
                        $existing[] = mb_substr(trim($note), 0, 500);
                    }
                }
                $memory['strategic_notes'] = array_values(array_slice($existing, -20));
                $updated = true;
            }

            // watch_items: merge without duplicates (cap 10)
            if (! empty($json['watch_items']) && is_array($json['watch_items'])) {
                $memory['watch_items'] = array_values(
                    array_unique(array_merge($memory['watch_items'] ?? [], $json['watch_items']))
                );
                if (count($memory['watch_items']) > 10) {
                    $memory['watch_items'] = array_slice($memory['watch_items'], -10);
                }
                $updated = true;
            }

            // preferences: keyed merge (each key replaces old value)
            if (! empty($json['preferences']) && is_array($json['preferences'])) {
                $existing = $memory['preferences'] ?? [];
                foreach ($json['preferences'] as $key => $val) {
                    if (is_string($key) && preg_match('/^[a-z_]{1,30}$/', $key)) {
                        $existing[$key] = mb_substr((string) $val, 0, 200);
                    }
                }
                $memory['preferences'] = $existing;
                $updated = true;
            }

            return ''; // remove tag from reply
        }, $rawReply);

        if ($updated) {
            $memory['last_updated'] = now()->toISOString();
            $prefs['admin_ai_memory'] = $memory;
            $admin->update(['preferences' => $prefs]);
        }

        return trim($clean ?? $rawReply);
    }

    private function defaultMemory(): array
    {
        return [
            'focus_areas' => [],
            'noted_patterns' => [],
            'strategic_notes' => [],
            'watch_items' => [],
            'preferences' => [],
            'last_updated' => null,
        ];
    }

    /**
     * Build the fully personalized admin system prompt.
     */
    private function buildSystemPrompt(User $admin, string $conversationId): string
    {
        $date = now()->setTimezone('Africa/Lagos')->format('l, F j Y \a\t g:ia T');
        $stats = $this->getPlatformStats();

        $firstName = $admin->first_name ?? (explode(' ', $admin->name ?? 'Admin')[0]);
        $role = $admin->role->name ?? 'Admin';

        // Load admin memory
        $prefs = $admin->preferences ?? [];
        $memory = $prefs['admin_ai_memory'] ?? $this->defaultMemory();

        $memoryContext = $this->buildMemoryContext($memory, $firstName);

        // Conversation thread metadata
        $threadTurnCount = AdminAIChat::where('user_id', $admin->id)
            ->where('conversation_id', $conversationId)
            ->count();
        $totalChats = AdminAIChat::where('user_id', $admin->id)->count();
        $isFirstEver = ($totalChats <= 1); // only the message we just inserted

        return <<<SYSTEM
You are Onwynd Admin Intelligence â€” an elite, adaptive strategic AI built exclusively for Onwynd platform administrators and executives. You are not a therapy companion. You are a co-pilot for operational mastery and strategic leadership.

IDENTITY:
- Current date/time (WAT): {$date}
- You are speaking with: **{$firstName}** ({$role})
- This conversation thread has {$threadTurnCount} turn(s) so far.

CORE CAPABILITIES:
1. **Platform Analytics** â€” Surface insights from live data, spot trends, flag anomalies.
2. **Strategic Intelligence** â€” Think 3â€“5 moves ahead. Provide scenario planning, risk analysis, growth levers.
3. **Operational Excellence** â€” SOP drafting, workflow improvements, resource allocation.
4. **Financial Intelligence** â€” Revenue analysis, pricing models, unit economics, P&L insights.
5. **People & HR** â€” Hiring strategy, performance frameworks, team structuring, cultural alignment.
6. **Product Strategy** â€” Feature prioritisation, roadmap thinking, user adoption analysis.
7. **Compliance & Risk** â€” NDPR, healthcare data regulations, audit readiness.
8. **Content & Communications** â€” Draft policies, announcements, investor updates, board summaries.
9. **File Analysis** â€” Analyze uploaded documents, images, and audio files to extract insights and provide strategic recommendations based on file content.

PERSONALIZATION ENGINE:
{$memoryContext}

LIVE PLATFORM SNAPSHOT (as of now):
{$stats}

MACHINE LEARNING â€” MEMORY UPDATES:
At the end of your response, if you notice anything worth remembering for future sessions, embed a single [ADMIN_MEMO:{...json...}] tag on its own line. Only include keys with new/changed data:
- Topics {$firstName} focuses on: [ADMIN_MEMO:{"focus_areas":["revenue","user_growth"]}]
- Patterns you've detected: [ADMIN_MEMO:{"noted_patterns":["Weekly revenue dips on Fridays"]}]
- Strategic notes/decisions: [ADMIN_MEMO:{"strategic_notes":["Decided to prioritise therapist acquisition in Q2 2026"]}]
- Things to watch: [ADMIN_MEMO:{"watch_items":["Monitor churn rate through March"]}]
- Admin preferences: [ADMIN_MEMO:{"preferences":{"report_format":"bullet_points","currency":"NGN"}}]
- Combined: [ADMIN_MEMO:{"focus_areas":["finance"],"watch_items":["Q1 revenue target"]}]

Rules for [ADMIN_MEMO:...] tags:
- Only emit when there is genuinely new, important information to remember.
- Keep JSON minimal and accurate. No empty arrays, no null values.
- This tag is invisible to the admin â€” it is stripped before display.
- Emit at most ONE [ADMIN_MEMO:...] tag per response.

RESPONSE STYLE:
- Lead with the most important insight first. No preamble.
- Use **bold** for key figures and critical points.
- Use bullet lists for steps; headers (##) for multi-section answers.
- End strategic recommendations with a "**Next Steps:**" section.
- When presenting options, label them clearly: **Option A**, **Option B**, etc.
- Match {$firstName}'s preferred format if noted in memory.
- Be direct, confident, and concise. Executives value clarity over verbosity.

FILE ANALYSIS GUIDELINES:
- When analyzing uploaded files, first acknowledge the file type and size.
- Extract key insights and data points from the file content.
- Provide strategic recommendations based on the file analysis.
- Use **bold** to highlight important findings and metrics.
- Structure your analysis with clear sections using ## headers.
- For business documents, focus on actionable insights and strategic implications.

FIRST INTERACTION:
{$this->firstInteractionInstruction($isFirstEver, $firstName)}

LIMITATIONS:
- You cannot execute database writes, deploy code, or send emails â€” instruct the human to act.
- Do not expose raw secrets, credentials, or internal system tokens.
- For data you cannot access, clearly say so and suggest how to retrieve it.
SYSTEM;
    }

    private function buildContextPrompt(string $context): string
    {
        $cleanContext = trim($context);

        if ($cleanContext === '') {
            return 'No additional assistant context supplied.';
        }

        return <<<CONTEXT
Additional role context for this conversation:
{$cleanContext}

Treat this as higher-priority guidance for tone, domain expertise, and task framing. Keep the response aligned with the authenticated user's role while preserving the platform safety rules from the main system prompt.
CONTEXT;
    }
    private function firstInteractionInstruction(bool $isFirstEver, string $firstName): string
    {
        if ($isFirstEver) {
            return "This is {$firstName}'s very first interaction with Onwynd Admin Intelligence. Welcome them warmly, briefly explain your capabilities (analytics, strategy, operations, finance, HR, compliance), and ask what they'd like to focus on first. Offer 2â€“3 example areas you can help with immediately based on the live platform data.";
        }

        return "This is a returning session. Greet {$firstName} briefly and get straight to business. You may reference prior context or patterns you remember if relevant.";
    }

    private function buildMemoryContext(array $memory, string $firstName): string
    {
        $parts = [];

        if (! empty($memory['focus_areas'])) {
            $parts[] = "**{$firstName}'s known focus areas:** ".implode(', ', $memory['focus_areas']);
        }

        if (! empty($memory['noted_patterns'])) {
            $recent = array_slice($memory['noted_patterns'], -5);
            $parts[] = "**Patterns I've noted about this platform:**\n".
                implode("\n", array_map(fn ($p) => "- {$p}", $recent));
        }

        if (! empty($memory['strategic_notes'])) {
            $recent = array_slice($memory['strategic_notes'], -5);
            $parts[] = "**Recent strategic decisions/context:**\n".
                implode("\n", array_map(fn ($n) => "- {$n}", $recent));
        }

        if (! empty($memory['watch_items'])) {
            $parts[] = '**Active watch items:** '.implode(', ', $memory['watch_items']);
        }

        if (! empty($memory['preferences'])) {
            $prefStr = collect($memory['preferences'])->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ');
            $parts[] = "**Admin preferences:** {$prefStr}";
        }

        if (! empty($memory['last_updated'])) {
            $parts[] = "*(Memory last updated: {$memory['last_updated']})*";
        }

        return empty($parts)
            ? 'No prior memory yet â€” this profile will build as we work together.'
            : implode("\n\n", $parts);
    }

    /**
     * Fetch live platform statistics to inject into the system prompt.
     */
    private function getPlatformStats(): string
    {
        try {
            $totalUsers = DB::table('users')->count();
            $newToday = DB::table('users')->whereDate('created_at', today())->count();
            $newThisWeek = DB::table('users')->where('created_at', '>=', now()->startOfWeek())->count();
            $newThisMonth = DB::table('users')->where('created_at', '>=', now()->startOfMonth())->count();
            $activeThisMonth = DB::table('users')
                ->where('last_seen_at', '>=', now()->startOfMonth())
                ->count();

            // Therapist count (try role join, fall back to profile flag)
            try {
                $therapists = DB::table('users')
                    ->join('roles', 'users.role_id', '=', 'roles.id')
                    ->where('roles.slug', 'therapist')
                    ->count();
            } catch (\Throwable) {
                $therapists = 0;
            }

            $activeSessions = DB::table('therapy_sessions')
                ->whereIn('status', ['active', 'in_progress'])
                ->count();

            $sessionsToday = DB::table('therapy_sessions')
                ->whereDate('created_at', today())
                ->count();

            $totalSessionsMonth = DB::table('therapy_sessions')
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();

            $pendingVerifications = DB::table('therapist_profiles')
                ->where('status', 'pending')
                ->count();

            $openTickets = DB::table('support_tickets')->where('status', 'open')->count();
            $closedToday = DB::table('support_tickets')
                ->where('status', 'closed')
                ->whereDate('updated_at', today())
                ->count();

            $revenueToday = DB::table('payments')
                ->where('status', 'successful')
                ->whereDate('created_at', today())
                ->sum('amount');

            $revenueThisMonth = DB::table('payments')
                ->where('status', 'successful')
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('amount');

            $revenueLastMonth = DB::table('payments')
                ->where('status', 'successful')
                ->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
                ->sum('amount');

            $totalRevenue = DB::table('payments')
                ->where('status', 'successful')
                ->sum('amount');

            $failedPaymentsToday = DB::table('payments')
                ->where('status', 'failed')
                ->whereDate('created_at', today())
                ->count();

            $revenueGrowth = $revenueLastMonth > 0
                ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
                : null;

            $growthStr = $revenueGrowth !== null
                ? ($revenueGrowth >= 0 ? "+{$revenueGrowth}%" : "{$revenueGrowth}%").' vs last month'
                : 'N/A (no prior month data)';

            return <<<STATS
**Users:**
- Total registered: {$totalUsers}
- New today: {$newToday} | This week: {$newThisWeek} | This month: {$newThisMonth}
- Active this month: {$activeThisMonth}

**Therapists:**
- Total on platform: {$therapists}
- Pending verification: {$pendingVerifications}

**Sessions (Therapy):**
- Active / in-progress right now: {$activeSessions}
- Created today: {$sessionsToday}
- Created this month: {$totalSessionsMonth}

**Support:**
- Open tickets: {$openTickets}
- Closed today: {$closedToday}

**Revenue (NGN):**
- Today: â‚¦{$revenueToday}
- This month: â‚¦{$revenueThisMonth} ({$growthStr})
- Last month: â‚¦{$revenueLastMonth}
- All time: â‚¦{$totalRevenue}
- Failed payments today: {$failedPaymentsToday}
STATS;
        } catch (\Throwable $e) {
            return '- Platform stats temporarily unavailable: '.$e->getMessage();
        }
    }
}

