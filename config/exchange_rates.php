<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Exchange Rate Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file controls how exchange rates are managed and
    | retrieved in the application. You can configure the default source,
    | caching behavior, and fallback rates.
    |
    */

    'default_source' => env('EXCHANGE_RATE_SOURCE', 'database'), // database, api, config

    'cache' => [
        'enabled' => env('EXCHANGE_RATE_CACHE_ENABLED', true),
        'ttl' => env('EXCHANGE_RATE_CACHE_TTL', 3600), // 1 hour in seconds
        'key_prefix' => 'exchange_rates',
    ],

    'api' => [
        'provider' => env('EXCHANGE_RATE_API_PROVIDER', 'exchangerate-api'), // exchangerate-api, fixer, openexchangerates
        'key' => env('EXCHANGE_RATE_API_KEY'),
        'base_url' => env('EXCHANGE_RATE_API_URL'),
        'timeout' => env('EXCHANGE_RATE_API_TIMEOUT', 30),
        'retry_attempts' => env('EXCHANGE_RATE_API_RETRY_ATTEMPTS', 3),
    ],

    'fallback_rates' => [
        'NGN' => 1.0,
        'USD' => 0.000625, // 1 USD = ~1600 NGN
        'GBP' => 0.00078,  // 1 GBP = ~1280 NGN
        'EUR' => 0.00068,  // 1 EUR = ~1470 NGN
        'JPY' => 0.1,      // 1 JPY = ~10 NGN (approximate)
        'CAD' => 0.00082,  // 1 CAD = ~1220 NGN (approximate)
        'AUD' => 0.00075,  // 1 AUD = ~1333 NGN (approximate)
    ],

    'base_currency' => env('EXCHANGE_RATE_BASE_CURRENCY', 'NGN'),

    'update_frequency' => env('EXCHANGE_RATE_UPDATE_FREQUENCY', 'hourly'), // hourly, daily, weekly

    'decimal_places' => env('EXCHANGE_RATE_DECIMAL_PLACES', 10),

    'min_rate' => env('EXCHANGE_RATE_MIN', 0.000001), // Minimum allowed rate
    'max_rate' => env('EXCHANGE_RATE_MAX', 1000000),  // Maximum allowed rate

    'notification' => [
        'enabled' => env('EXCHANGE_RATE_NOTIFICATION_ENABLED', true),
        'threshold' => env('EXCHANGE_RATE_NOTIFICATION_THRESHOLD', 5), // Notify if rate changes by more than 5%
        'channels' => ['mail', 'slack'], // Notification channels
    ],
];
