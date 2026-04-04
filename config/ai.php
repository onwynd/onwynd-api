<?php

return [
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),
    'high_performance_provider' => env('AI_HIGH_PERFORMANCE_PROVIDER', 'anthropic'),
    'low_cost_provider' => env('AI_LOW_COST_PROVIDER', 'google'),

    'models' => [
        'openai' => [
            'complex' => 'gpt-4-turbo-preview',
            'chat' => 'gpt-3.5-turbo',
            'fast' => 'gpt-3.5-turbo',
            'default' => 'gpt-4-turbo-preview',
        ],
        'anthropic' => [
            'complex' => 'claude-3-opus-20240229',
            'chat' => 'claude-3-sonnet-20240229',
            'fast' => 'claude-3-haiku-20240307',
            'default' => 'claude-3-sonnet-20240229',
        ],
        'google' => [
            'complex' => 'gemini-pro', // Placeholder for Ultra if available
            'chat' => 'gemini-pro',
            'fast' => 'gemini-pro',
            'default' => 'gemini-pro',
        ],
    ],
];
