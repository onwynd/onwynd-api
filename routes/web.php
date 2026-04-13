<?php

use Illuminate\Support\Facades\Route;

// Some middleware (e.g. Authenticate) may redirect unauthenticated browser requests
// to a named "login" route. This project is API-first and doesn't ship a login page,
// so we define a minimal route to prevent RouteNotFoundException noise.
Route::get('/login', function () {
    return response()->json([
        'message' => 'Unauthenticated.',
    ], 401);
})->name('login');

Route::get('/', function () {
    return view('onwynd');
});

Route::post('/internal/unlock', function (\Illuminate\Http\Request $request) {
    $key = env('INTERNAL_ACCESS_KEY');
    if (! $key || $request->input('key') !== $key) {
        abort(403);
    }
    return response()->json([
        'links' => [
            ['label' => 'Admin Dashboard',  'href' => '/admin'],
            ['label' => 'API Docs',         'href' => '/api/documentation'],
            ['label' => 'Telescope',        'href' => '/telescope'],
            ['label' => 'Queue Monitor',    'href' => '/horizon'],
        ],
        'env'     => config('app.env'),
        'php'     => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
        'version' => config('app.version', '1.0.0'),
    ]);
})->middleware('throttle:5,1'); // 5 attempts per minute

Route::get('/test/ai', function () {
    return view('test-ai');
});
