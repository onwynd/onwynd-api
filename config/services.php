<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'model' => App\Models\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4-turbo-preview'),
        'pricing' => [
            'prompt_per_1k' => env('OPENAI_PROMPT_PER_1K', 0.3),
            'completion_per_1k' => env('OPENAI_COMPLETION_PER_1K', 0.6),
        ],
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'pricing' => [
            'prompt_per_1k' => env('GROQ_PROMPT_PER_1K', 0.0),
            'completion_per_1k' => env('GROQ_COMPLETION_PER_1K', 0.0),
        ],
    ],

    'grok' => [
        'api_key' => env('GROK_API_KEY'),
        'model' => env('GROK_MODEL', 'grok-1'),
        'pricing' => [
            'prompt_per_1k' => env('GROK_PROMPT_PER_1K', 0.0),
            'completion_per_1k' => env('GROK_COMPLETION_PER_1K', 0.0),
        ],
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-pro'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-3-sonnet-20240229'),
    ],

    'cohere' => [
        'api_key' => env('COHERE_API_KEY'),
        'model' => env('COHERE_MODEL', 'command-r'),
    ],

    'deepseek' => [
        'api_key' => env('DEEPSEEK_API_KEY'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
    ],

    'perplexity' => [
        'api_key' => env('PERPLEXITY_API_KEY'),
        'model' => env('PERPLEXITY_MODEL', 'llama-3-sonar-large-32k-online'),
    ],

    'ai' => [
        'default' => env('AI_PROVIDER', env('AI_DRIVER', 'openai')),
        'bypass_llm_on_risk' => env('AI_BYPASS_LLM_ON_RISK', true),
    ],

    'termii' => [
        'api_key' => env('TERMII_API_KEY'),
        'sender_id' => env('TERMII_SENDER_ID', 'ONWYND'),
    ],

    'whatsapp' => [
        'phone_number_id'    => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token'       => env('WHATSAPP_ACCESS_TOKEN'),
        'business_account_id'=> env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    ],

    // QR-linked-device microservice (whatsapp-service/)
    'whatsapp_microservice' => [
        'url'    => env('WHATSAPP_MICROSERVICE_URL', 'http://localhost:3001'),
        'secret' => env('WHATSAPP_MICROSERVICE_SECRET', ''),
    ],

    'termii' => [
        'api_key'   => env('TERMII_API_KEY', ''),
        'sender_id' => env('TERMII_SENDER_ID', 'ONWYND'),
    ],

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'payment_url' => env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co'),
        'merchant_email' => env('MERCHANT_EMAIL'),
    ],

    'flutterwave' => [
        'public_key' => env('FLW_PUBLIC_KEY'),
        'secret_key' => env('FLW_SECRET_KEY'),
        'secret_hash' => env('FLW_SECRET_HASH'),
    ],

    'klump' => [
        'public_key' => env('KLUMP_PUBLIC_KEY'),
        'secret_key' => env('KLUMP_SECRET_KEY'),
        'base_url' => env('KLUMP_BASE_URL', 'https://api.useklump.com/v1'),
    ],

    'dodopayments' => [
        'secret_key' => env('DODOPAYMENTS_SECRET_KEY'),
        'webhook_secret' => env('DODOPAYMENTS_WEBHOOK_SECRET'),
        'live_mode' => env('DODOPAYMENTS_LIVE_MODE', false),
        'default_product_id' => env('DODOPAYMENTS_DEFAULT_PRODUCT_ID', 'onwynd_service'),
    ],
    'transcriber' => [
        'driver' => env('TRANSCRIBER_DRIVER', 'openai'),
        'verify' => env('TRANSCRIBER_VERIFY_SSL', true),
        'retention_hours' => env('CHAT_VOICE_RETENTION_HOURS', 24),
        'local_whisper' => [
            'url' => env('LOCAL_WHISPER_URL', 'http://127.0.0.1:5001/transcribe'),
        ],
    ],

    // WyndChat (help.onwynd.com) — real-time webhook sync
    'wyndchat' => [
        'url' => env('WYNDCHAT_URL', 'https://help.onwynd.com'),
        'webhook_secret' => env('WYNDCHAT_WEBHOOK_SECRET', ''),
    ],

    // Web Push / VAPID
    // Generate keys with: php artisan vapid:generate  (requires minishlink/web-push)
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@onwynd.com'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    // Firebase Cloud Messaging (HTTP v1 API)
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    // Ollama local LLM (offline fallback — Phi-3.5-mini)
    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
        'model' => env('OLLAMA_MODEL', 'phi3.5'),
    ],

    // Onwynd platform settings
    'onwynd' => [
        'crisis_email' => env('CRISIS_OPS_EMAIL', 'clinical@onwynd.com'),
    ],

];
