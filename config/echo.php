<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel Echo Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file is used by the Laravel Echo javascript library.
    | You can verify that your Echo configuration is correct by running
    | the "php artisan echo:config" command.
    |
    */

    'broadcaster' => 'reverb',

    'reverb' => [
        'key' => env('REVERB_APP_KEY'),
        'secret' => env('REVERB_APP_SECRET'),
        'app_id' => env('REVERB_APP_ID'),
        'options' => [
            'host' => env('REVERB_HOST', 'localhost'),
            'port' => env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'http'),
            'useTLS' => env('REVERB_SCHEME') === 'https',
        ],
        'client_options' => [
            // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
        ],
    ],

];
