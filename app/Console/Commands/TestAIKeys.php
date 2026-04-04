<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestAIKeys extends Command
{
    protected $signature = 'ai:test-keys';

    protected $description = 'Test Groq and Grok API keys by calling provider endpoints';

    public function handle(): int
    {
        $tested = false;

        // Test Groq
        $groqKey = config('services.groq.api_key');
        if (empty($groqKey)) {
            $groqKey = $this->readEnvValue('GROQ_API_KEY');
        }
        if (! empty($groqKey)) {
            $tested = true;
            $this->info('Testing Groq API key (models endpoint)...');
            try {
                $resp = Http::withOptions(['verify' => false])->withHeaders([
                    'Authorization' => 'Bearer '.$groqKey,
                ])->get('https://api.groq.com/openai/v1/models');

                if ($resp->successful()) {
                    $data = $resp->json();
                    $models = data_get($data, 'data', []);
                    $count = is_array($models) ? count($models) : 0;
                    $sample = collect($models)->take(5)->pluck('id');
                    $this->info("Groq OK. Models: {$count}. Sample: ".implode(', ', $sample->all()));
                } else {
                    $this->error('Groq models request failed: HTTP '.$resp->status());
                    $this->line(substr($resp->body(), 0, 300));
                }
            } catch (\Throwable $e) {
                $this->error('Groq test error: '.$e->getMessage());
            }
        } else {
            $this->line('Groq API key not set.');
        }

        // Test Grok (x.ai)
        $grokKey = config('services.grok.api_key');
        if (empty($grokKey)) {
            $grokKey = $this->readEnvValue('GROK_API_KEY');
        }
        if (! empty($grokKey)) {
            $tested = true;
            $this->info('Testing Grok API key (models or chat endpoint)...');

            try {
                $resp = Http::withOptions(['verify' => false])->withHeaders([
                    'Authorization' => 'Bearer '.$grokKey,
                ])->get('https://api.x.ai/v1/models');

                if ($resp->successful()) {
                    $data = $resp->json();
                    $models = data_get($data, 'data', []);
                    $count = is_array($models) ? count($models) : 0;
                    $sample = collect($models)->take(5)->pluck('id');
                    $this->info("Grok OK. Models: {$count}. Sample: ".implode(', ', $sample->all()));
                } else {
                    $this->line('Grok /models failed, trying /chat/completions...');
                    $payload = [
                        'model' => config('services.grok.model', 'grok-1'),
                        'messages' => [
                            ['role' => 'user', 'content' => 'ping'],
                        ],
                        'temperature' => 0,
                    ];

                    $resp2 = Http::withOptions(['verify' => false])->withHeaders([
                        'Authorization' => 'Bearer '.$grokKey,
                    ])->post('https://api.x.ai/v1/chat/completions', $payload);

                    if ($resp2->successful()) {
                        $content = data_get($resp2->json(), 'choices.0.message.content');
                        $this->info('Grok OK. Chat response: '.substr((string) $content, 0, 80));
                    } else {
                        $this->error('Grok chat request failed: HTTP '.$resp2->status());
                        $this->line(substr($resp2->body(), 0, 300));
                    }
                }
            } catch (\Throwable $e) {
                $this->error('Grok test error: '.$e->getMessage());
            }
        } else {
            $this->line('Grok API key not set.');
        }

        if (! $tested) {
            $this->warn('No provider keys set. Configure GROQ_API_KEY or GROK_API_KEY in .env.');
        }

        return self::SUCCESS;
    }

    private function readEnvValue(string $key): ?string
    {
        $path = base_path('.env');
        if (! file_exists($path)) {
            return null;
        }
        $content = file_get_contents($path) ?: '';
        $pattern = '/^\\s*'.preg_quote($key, '/').'\\s*=\\s*(.+)$/m';
        if (preg_match($pattern, $content, $m)) {
            return trim($m[1]);
        }

        return null;
    }
}
