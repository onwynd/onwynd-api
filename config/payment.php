<?php

return [
    'currency' => [
        'primary' => env('PAYMENT_CURRENCY_PRIMARY', 'NGN'),
        'fallback' => env('PAYMENT_CURRENCY_FALLBACK', 'USD'),
        'supported' => ['NGN', 'USD', 'GBP', 'EUR'],
    ],

    'limits' => [
        'min_amount' => env('PAYMENT_MIN_AMOUNT', 100),
        'max_amount' => env('PAYMENT_MAX_AMOUNT', 100000000),
    ],

    'timeout' => env('PAYMENT_TIMEOUT_SECONDS', 60),
    'retry_attempts' => env('PAYMENT_RETRY_ATTEMPTS', 3),
    'log_level' => env('PAYMENT_LOG_LEVEL', 'info'),

    'gateways' => [
        'default' => env('PAYMENT_DEFAULT_GATEWAY', 'paystack'),

        'paystack' => [
            'enabled' => env('PAYSTACK_SECRET_KEY') !== null,
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'base_url' => 'https://api.paystack.co',
            'webhook_url' => env('PAYSTACK_WEBHOOK_URL'),
            'currencies' => ['NGN', 'USD', 'GBP', 'EUR'],
            'priority' => 1,
            'description' => 'Paystack - Nigerian Payment Gateway',
        ],

        'flutterwave' => [
            'enabled' => env('FLUTTERWAVE_SECRET_KEY') !== null,
            'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
            'base_url' => 'https://api.flutterwave.com/v3',
            'webhook_url' => env('FLUTTERWAVE_WEBHOOK_URL'),
            'currencies' => ['NGN', 'USD', 'GBP', 'EUR', 'ZAR', 'KES'],
            'priority' => 2,
            'description' => 'Flutterwave - Multi-region Payment Gateway',
        ],

        'stripe' => [
            'enabled' => env('STRIPE_SECRET_KEY') !== null,
            'public_key' => env('STRIPE_PUBLIC_KEY'),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'base_url' => 'https://api.stripe.com/v1',
            'webhook_url' => env('STRIPE_WEBHOOK_URL'),
            'currencies' => ['USD', 'GBP', 'EUR', 'AUD', 'CAD', 'CHF', 'CNY', 'DKK', 'HKD', 'INR', 'JPY', 'MXN', 'MYR', 'NOK', 'NZD', 'SEK', 'SGD', 'ZAR'],
            'priority' => 3,
            'description' => 'Stripe - International Payment Gateway',
        ],
    ],

    'platform_account' => [
        'bank_account_number' => env('PLATFORM_BANK_ACCOUNT_NUMBER'),
        'bank_code' => env('PLATFORM_BANK_CODE'),
        'bank_account_name' => env('PLATFORM_BANK_ACCOUNT_NAME', 'ONWYND TECHNOLOGIES'),
        'paystack_account' => env('PLATFORM_PAYSTACK_ACCOUNT'),
        'flutterwave_account' => env('PLATFORM_FLUTTERWAVE_ACCOUNT'),
    ],

    'naira' => [
        'symbol' => '₦',
        'code' => 'NGN',
        'decimals' => 2,
        'kobo_multiplier' => 100,
        'vat_rate' => 0.075, // 7.5% VAT
        'min_amount_naira' => 100,
        'max_amount_naira' => 100000000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway Architecture Classes
    |--------------------------------------------------------------------------
    |
    | The ordered list of PaymentGatewayInterface implementations used by
    | PaymentGatewayFactory::resolve(). Each class must implement supports()
    | to declare which currencies it handles.
    |
    | Future gateways to add here:
    |   \App\Services\Payment\FlutterwaveGateway::class  — GHS, KES, ZAR, ...
    |   \App\Services\Payment\MercadoPagoGateway::class  — MXN
    |   \App\Services\Payment\AdyenGateway::class        — EUR, GBP, ...
    |
    */
    'gateway_classes' => [
        \App\Services\Payment\PaystackGateway::class,
        \App\Services\Payment\StripeGateway::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Currencies With Configured Gateways
    |--------------------------------------------------------------------------
    |
    | Currencies that have at least one active gateway. Used for validation
    | and UI currency selectors. Keep in sync with gateway_classes above.
    |
    | Future: 'GHS', 'KES', 'ZAR', 'EUR', 'GBP', 'MXN'
    |
    */
    'supported_currencies' => ['NGN', 'USD'],
];
