<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global security headers
        $middleware->append(\App\Http\Middleware\AddSecurityHeaders::class);

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'subscription' => \App\Http\Middleware\CheckSubscription::class,
            'track.activity' => \App\Http\Middleware\TrackUserActivity::class,
            'verified' => \App\Http\Middleware\EnsureEmailVerified::class,
            'clinical_advisor' => \App\Http\Middleware\IsClinicalAdvisor::class,
            'feature' => \App\Http\Middleware\CheckFeatureEnabled::class,
            'activity.quota' => \App\Http\Middleware\ActivityQuotaMiddleware::class,
            'ai.quota' => \App\Http\Middleware\AiChatQuotaMiddleware::class,
            'ambassador' => \App\Http\Middleware\CheckAmbassador::class,
            'institutional.paywall' => \App\Http\Middleware\InstitutionalPaywall::class,
        ]);

        // Promote secure auth cookies to Bearer auth before route middleware runs.
        $middleware->appendToGroup('api', \App\Http\Middleware\UseAuthTokenCookie::class);

        // Apply activity tracking to all authenticated API requests
        $middleware->appendToGroup('api', \App\Http\Middleware\TrackUserActivity::class);

        // Capture X-Device-Fingerprint header on all API requests for security/fraud detection
        $middleware->appendToGroup('api', \App\Http\Middleware\CaptureDeviceFingerprint::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }

            return $request->expectsJson();
        });
    })->create();
