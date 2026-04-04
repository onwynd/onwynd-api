<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Crisis Operations Email
    |--------------------------------------------------------------------------
    | Recipient for clinical crisis escalations triggered by the AI Companion.
    */
    'crisis_email' => env('CRISIS_OPS_EMAIL', 'clinical@onwynd.com'),

    /*
    |--------------------------------------------------------------------------
    | Support Email
    |--------------------------------------------------------------------------
    */
    'support_email' => env('SUPPORT_EMAIL', 'hello@onwynd.com'),

    /*
    |--------------------------------------------------------------------------
    | Display Exchange Rate (NGN per 1 USD)
    |--------------------------------------------------------------------------
    | Used only for display purposes in the UI. Not used for financial
    | calculations — those use live rates from the payment gateway.
    */
    'display_exchange_rate' => (int) env('DISPLAY_EXCHANGE_RATE', 1600),

    /*
    |--------------------------------------------------------------------------
    | Platform Commission Rate
    |--------------------------------------------------------------------------
    | The percentage of each session fee retained by the platform (0–1).
    | Default: 0.20 (20%). Therapists receive 1 - commission_rate.
    */
    'commission_rate' => (float) env('PLATFORM_COMMISSION_RATE', 0.20),

];
