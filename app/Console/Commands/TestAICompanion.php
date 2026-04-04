<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\AI\AICompanionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TestAICompanion extends Command
{
    protected $signature = 'ai:test-companion {--message=Hello, can you share a quick well-being tip?}';

    protected $description = 'Invoke AICompanionService and print the assistant response, with provider fallback';

    public function handle(): int
    {
        $message = (string) $this->option('message');
        $user = $this->getUser();
        $this->info('Testing AI Companion (provider: '.config('services.ai.default', 'openai').')');

        try {
            $result = DB::transaction(function () use ($user, $message) {
                $svc = new AICompanionService;

                return $svc->chat($user, $message, null);
            });

            $this->printResult($result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->warn('Service invocation failed, falling back to direct provider call: '.$e->getMessage());

            return $this->providerFallback($message);
        }
    }

    private function getUser(): User
    {
        $u = User::query()->first();
        if ($u) {
            return $u;
        }
        $email = 'test+'.time().'@example.com';
        $role = Role::where('slug', 'patient')->first();
        if (! $role) {
            $role = new Role;
            $role->name = 'Patient';
            $role->slug = 'patient';
            $role->permissions = [];
            $role->save();
        }
        $u = new User;
        $u->uuid = (string) Str::uuid();
        $u->first_name = 'Test';
        $u->last_name = 'User';
        $u->email = $email;
        $u->password = 'password';
        $u->is_active = true;
        $u->role_id = $role->id;
        $u->gender = 'other';
        $u->save();

        return $u;
    }

    private function printResult(array $result): void
    {
        $this->line('Conversation ID: '.($result['conversation_id'] ?? 'n/a'));
        $this->line('Assistant: '.substr((string) ($result['message'] ?? ''), 0, 500));
        $usage = $result['usage'] ?? [];
        $this->line('Prompt tokens: '.($usage['prompt_tokens'] ?? 'n/a'));
        $this->line('Completion tokens: '.($usage['completion_tokens'] ?? 'n/a'));
        $this->line('Estimated cost: '.($usage['cost'] ?? 'n/a'));
        $this->line('Crisis keywords: '.((isset($result['contains_crisis_keywords']) && $result['contains_crisis_keywords']) ? 'yes' : 'no'));
    }

    private function providerFallback(string $message): int
    {
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

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are Onwynd AI Companion, supportive and safe.'],
                ['role' => 'user', 'content' => $message],
            ],
            'temperature' => 0.7,
        ];

        try {
            $resp = Http::withOptions(['verify' => false])->withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
            ])->post($base.'/chat/completions', $payload);

            if ($resp->successful()) {
                $json = $resp->json();
                $assistantText = (string) (data_get($json, 'choices.0.message.content') ?? '');
                $result = [
                    'conversation_id' => null,
                    'message' => $assistantText,
                    'usage' => [
                        'prompt_tokens' => data_get($json, 'usage.prompt_tokens'),
                        'completion_tokens' => data_get($json, 'usage.completion_tokens'),
                        'cost' => null,
                    ],
                    'contains_crisis_keywords' => false,
                ];
                $this->printResult($result);

                return self::SUCCESS;
            }
            $this->error('Provider call failed: HTTP '.$resp->status());
            $this->line(substr($resp->body(), 0, 300));

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Provider call error: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
