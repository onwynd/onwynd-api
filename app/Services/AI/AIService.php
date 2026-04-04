<?php

namespace App\Services\AI;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected $driver;

    protected $apiKey;

    protected $baseUrl;

    protected $model;

    // Fallback chain: primary → fallback1 → fallback2
    // Primary: groq (llama-3.3-70b-versatile)
    // Fallback 1: openai (gpt-4o-mini)
    // Fallback 2: ollama (phi3.5, local)
    protected const FALLBACK_CHAIN = ['groq', 'openai', 'ollama'];

    public function __construct()
    {
        // Prioritize DB setting, fallback to groq as primary
        $this->driver = Setting::where('key', 'ai_default_driver')->value('value')
            ?? config('services.ai.default', 'groq');

        $this->configureDriver();
    }

    protected function sanitizeAIResponse($content)
    {
        if (! $content) {
            return $content;
        }

        // Fix common word break issues and spacing problems
        $content = preg_replace('/\s+/', ' ', $content); // Normalize whitespace
        $content = preg_replace('/\b(\w+)-\s*\n\s*(\w+)\b/', '$1$2', $content); // Fix word breaks
        $content = preg_replace('/\b(\w+)\s+\n\s*(\w+)\b/', '$1 $2', $content); // Fix split words

        // Fix common spelling patterns that AI often gets wrong
        $commonMisspellings = [
            'recieve' => 'receive',
            'seperate' => 'separate',
            'definately' => 'definitely',
            'occured' => 'occurred',
            'begining' => 'beginning',
            'untill' => 'until',
            'writting' => 'writing',
            'existance' => 'existence',
            'maintainance' => 'maintenance',
            'neccessary' => 'necessary',
            'priviledge' => 'privilege',
            'arguement' => 'argument',
            'consious' => 'conscious',
            'embarass' => 'embarrass',
            'exagerate' => 'exaggerate',
            'independant' => 'independent',
            'occassion' => 'occasion',
            'recomend' => 'recommend',
            'sucessful' => 'successful',
            'tommorrow' => 'tomorrow',
            'wierd' => 'weird',
        ];

        foreach ($commonMisspellings as $wrong => $correct) {
            $content = preg_replace('/\b'.preg_quote($wrong, '/').'\b/i', $correct, $content);
        }

        // Clean up any remaining formatting issues
        $content = trim($content);
        $content = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $content); // Normalize line breaks

        return $content;
    }

    protected function configureDriver()
    {
        // Helper to get setting or config
        $getSetting = function ($key, $defaultConfig) {
            return Setting::where('key', $key)->value('value') ?? config($defaultConfig);
        };

        switch ($this->driver) {
            case 'groq':
                $this->baseUrl = 'https://api.groq.com/openai/v1';
                $this->apiKey = $getSetting('ai_groq_api_key', 'services.groq.api_key');
                $this->model = $getSetting('ai_groq_model', 'services.groq.model') ?? 'llama-3.3-70b-versatile';
                break;
            case 'ollama':
                $this->baseUrl = $getSetting('ai_ollama_url', 'services.ollama.url') ?? 'http://localhost:11434/v1';
                $this->apiKey = 'ollama'; // Ollama doesn't require a real key but the header must be set
                $this->model = $getSetting('ai_ollama_model', 'services.ollama.model') ?? 'phi3.5';
                break;
            case 'grok':
                $this->baseUrl = 'https://api.x.ai/v1';
                $this->apiKey = $getSetting('ai_grok_api_key', 'services.grok.api_key');
                $this->model = $getSetting('ai_grok_model', 'services.grok.model') ?? 'grok-1';
                break;
            case 'gemini':
                $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';
                $this->apiKey = $getSetting('ai_gemini_api_key', 'services.gemini.api_key');
                $this->model = $getSetting('ai_gemini_model', 'services.gemini.model') ?? 'gemini-pro';
                break;
            case 'anthropic':
                $this->baseUrl = 'https://api.anthropic.com/v1';
                $this->apiKey = $getSetting('ai_anthropic_api_key', 'services.anthropic.api_key');
                $this->model = $getSetting('ai_anthropic_model', 'services.anthropic.model') ?? 'claude-3-sonnet-20240229';
                break;
            case 'cohere':
                $this->baseUrl = 'https://api.cohere.ai/v1';
                $this->apiKey = $getSetting('ai_cohere_api_key', 'services.cohere.api_key');
                $this->model = $getSetting('ai_cohere_model', 'services.cohere.model') ?? 'command-r';
                break;
            case 'deepseek':
                $this->baseUrl = 'https://api.deepseek.com';
                $this->apiKey = $getSetting('ai_deepseek_api_key', 'services.deepseek.api_key');
                $this->model = $getSetting('ai_deepseek_model', 'services.deepseek.model') ?? 'deepseek-chat';
                break;
            case 'perplexity':
                $this->baseUrl = 'https://api.perplexity.ai';
                $this->apiKey = $getSetting('ai_perplexity_api_key', 'services.perplexity.api_key');
                $this->model = $getSetting('ai_perplexity_model', 'services.perplexity.model') ?? 'llama-3-sonar-large-32k-online';
                break;
            case 'openai':
            default:
                $this->baseUrl = 'https://api.openai.com/v1';
                $this->apiKey = $getSetting('ai_openai_api_key', 'services.openai.api_key');
                $this->model = $getSetting('ai_openai_model', 'services.openai.model') ?? 'gpt-4o-mini';
                break;
        }
    }

    /**
     * Generate a response from the AI with automatic fallback cascade.
     *
     * Fallback chain (Section 9.6):
     *   Primary:    groq  (llama-3.3-70b-versatile)
     *   Fallback 1: openai (gpt-4o-mini)
     *   Fallback 2: ollama (phi3.5, local)
     *
     * Each provider is attempted in order. On failure (exception or non-2xx
     * response), the next provider is tried. Which provider served each
     * request is logged for the COO AI Operations dashboard.
     *
     * @param  string  $prompt
     * @param  array  $history  Chat history for context
     * @return string|null
     */
    public function generateResponse($prompt, $history = [])
    {
        // For non-fallback-chain drivers (gemini, anthropic, cohere), use direct dispatch
        if (in_array($this->driver, ['gemini', 'anthropic', 'cohere', 'grok', 'deepseek', 'perplexity'])) {
            return $this->dispatchToDriver($this->driver, $prompt, $history);
        }

        // Try the configured primary driver first, then cascade through fallback chain
        $driversToTry = array_unique(array_merge([$this->driver], self::FALLBACK_CHAIN));
        $originalDriver = $this->driver;

        foreach ($driversToTry as $driver) {
            // Configure for this driver if switching
            if ($driver !== $this->driver) {
                $this->driver = $driver;
                $this->configureDriver();
            }

            $result = $this->dispatchToDriver($driver, $prompt, $history);

            if ($result !== null) {
                if ($driver !== $originalDriver) {
                    Log::warning("AI fallback: served by '{$driver}' (primary '{$originalDriver}' failed)");
                }
                // Restore original driver config for subsequent calls
                if ($driver !== $originalDriver) {
                    $this->driver = $originalDriver;
                    $this->configureDriver();
                }

                return $result;
            }

            Log::warning("AI provider '{$driver}' failed, trying next in fallback chain");
        }

        // Restore original driver
        $this->driver = $originalDriver;
        $this->configureDriver();

        Log::error('All AI providers in fallback chain exhausted. No response generated.');

        return null;
    }

    /**
     * Dispatch to a specific driver by name.
     */
    protected function dispatchToDriver(string $driver, $prompt, $history): ?string
    {
        return match ($driver) {
            'gemini' => $this->generateGeminiResponse($prompt, $history),
            'anthropic' => $this->generateAnthropicResponse($prompt, $history),
            'cohere' => $this->generateCohereResponse($prompt, $history),
            default => $this->generateOpenAICompatibleResponse($prompt, $history), // groq, openai, ollama, grok, deepseek, perplexity
        };
    }

    protected function generateOpenAICompatibleResponse($prompt, $history)
    {
        try {
            $messages = $history;
            $messages[] = ['role' => 'user', 'content' => $prompt];

            // Ensure system message exists
            if (empty($history) || (isset($history[0]['role']) && $history[0]['role'] !== 'system')) {
                array_unshift($messages, [
                    'role' => 'system',
                    'content' => 'You are Onwynd AI, a mental health assistant. Provide empathetic, professional, and safe support.',
                ]);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 500,
            ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');

                return $this->sanitizeAIResponse($content);
            }

            Log::error("{$this->driver} Error: ".$response->body());

            return null;
        } catch (\Exception $e) {
            Log::error("{$this->driver} Exception: ".$e->getMessage());

            return null;
        }
    }

    protected function generateGeminiResponse($prompt, $history)
    {
        try {
            $contents = [];

            foreach ($history as $msg) {
                $role = $msg['role'] === 'user' ? 'user' : 'model';
                if ($msg['role'] === 'system') {
                    continue;
                }

                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $msg['content']]],
                ];
            }

            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ];

            $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::post($url, [
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 500,
                ],
            ]);

            if ($response->successful()) {
                return $response->json('candidates.0.content.parts.0.text');
            }

            Log::error('Gemini Error: '.$response->body());

            return null;
        } catch (\Exception $e) {
            Log::error('Gemini Exception: '.$e->getMessage());

            return null;
        }
    }

    protected function generateAnthropicResponse($prompt, $history)
    {
        try {
            $messages = [];
            $system = 'You are Onwynd AI, a mental health assistant. Provide empathetic, professional, and safe support.';

            foreach ($history as $msg) {
                if ($msg['role'] === 'system') {
                    $system = $msg['content'];

                    continue;
                }
                $messages[] = [
                    'role' => $msg['role'] === 'user' ? 'user' : 'assistant',
                    'content' => $msg['content'],
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $prompt];

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post("{$this->baseUrl}/messages", [
                'model' => $this->model,
                'max_tokens' => 1024,
                'system' => $system,
                'messages' => $messages,
            ]);

            if ($response->successful()) {
                return $response->json('content.0.text');
            }

            Log::error('Anthropic Error: '.$response->body());

            return null;
        } catch (\Exception $e) {
            Log::error('Anthropic Exception: '.$e->getMessage());

            return null;
        }
    }

    protected function generateCohereResponse($prompt, $history)
    {
        try {
            $chatHistory = [];
            foreach ($history as $msg) {
                if ($msg['role'] === 'system') {
                    continue;
                }
                $chatHistory[] = [
                    'role' => $msg['role'] === 'user' ? 'USER' : 'CHATBOT',
                    'message' => $msg['content'],
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/chat", [
                'model' => $this->model,
                'message' => $prompt,
                'chat_history' => $chatHistory,
                'preamble' => 'You are Onwynd AI, a mental health assistant. Provide empathetic, professional, and safe support.',
            ]);

            if ($response->successful()) {
                return $response->json('text');
            }

            Log::error('Cohere Error: '.$response->body());

            return null;
        } catch (\Exception $e) {
            Log::error('Cohere Exception: '.$e->getMessage());

            return null;
        }
    }

    public function analyzeSentiment($text)
    {
        $prompt = "Analyze the sentiment of the following text and return a JSON object with 'sentiment' (positive, negative, neutral), 'score' (0-10), and 'risk_level' (low, medium, high): \n\n\"{$text}\"";

        $response = $this->generateResponse($prompt);

        if ($response) {
            preg_match('/\{.*\}/s', $response, $matches);
            if (! empty($matches[0])) {
                return json_decode($matches[0], true);
            }
        }

        return ['sentiment' => 'neutral', 'score' => 5, 'risk_level' => 'low'];
    }

    public function generateAssessmentInterpretation($assessmentTitle, $rawScore, $answers, $percentage, $severity)
    {
        $answerSummary = count($answers) > 0 ? 'Responses to '.count($answers).' questions' : 'No answers recorded';

        $prompt = <<<PROMPT
Assessment: {$assessmentTitle}
Score: {$rawScore} ({$percentage}%)
Severity Level: {$severity}
Responses Summary: {$answerSummary}

Please provide:
1. A brief, empathetic interpretation of the assessment results
2. Personalized recommendations based on the score and severity level

Format your response as JSON with keys "interpretation" and "recommendations".
PROMPT;

        $response = $this->generateResponse($prompt);

        if ($response) {
            preg_match('/\{.*\}/s', $response, $matches);
            if (! empty($matches[0])) {
                $result = json_decode($matches[0], true);
                if (is_array($result) && isset($result['interpretation'])) {
                    return $result;
                }
            }
        }

        return [
            'interpretation' => 'Assessment completed. Please consult with a healthcare professional for personalized guidance.',
            'recommendations' => 'Consider speaking with a mental health professional for more detailed support.',
        ];
    }
}
