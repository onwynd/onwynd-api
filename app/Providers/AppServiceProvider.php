<?php

namespace App\Providers;

use App\Events\AI\CompanionRiskEscalationEvent;
use App\Events\AssessmentCompleted;
use App\Events\PaymentProcessed;
use App\Events\SessionBooked;
use App\Events\SessionCompleted;
use App\Events\UserCreated;
use App\Listeners\CreatePatientProfile;
use App\Listeners\LogMailFailed;
use App\Listeners\LogMailSent;
use App\Listeners\SendAssessmentResultEmail;
use App\Listeners\SendCompanionRiskEscalationAlert;
use App\Listeners\SendPaymentConfirmation;
use App\Listeners\SendSessionCompletionNotification;
use App\Listeners\SendSessionConfirmation;
use App\Listeners\SendWelcomeNotification;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\Payment\PaystackGateway::class, function ($app) {
            return new \App\Services\Payment\PaystackGateway(
                $app->make(\App\Services\PaymentService\PaystackService::class)
            );
        });

        $this->app->singleton(\App\Services\Payment\StripeGateway::class, function ($app) {
            return new \App\Services\Payment\StripeGateway(
                $app->make(\App\Services\PaymentService\StripeService::class)
            );
        });
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('ai-chat', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('booking', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip());
        });

        // Register model observers
        User::observe(UserObserver::class);

        // AI Companion risk escalation
        Event::listen(CompanionRiskEscalationEvent::class, [SendCompanionRiskEscalationAlert::class, 'handle']);

        // User registration → welcome emails + in-app notification (consolidated)
        Event::listen(UserCreated::class, [SendWelcomeNotification::class, 'handle']);

        // Create patient profile for users with patient role
        Event::listen(UserCreated::class, [CreatePatientProfile::class, 'handle']);

        // Payment confirmation email
        Event::listen(PaymentProcessed::class, [SendPaymentConfirmation::class, 'handle']);

        // Assessment result email (only sent for non-benign severity levels)
        Event::listen(AssessmentCompleted::class, [SendAssessmentResultEmail::class, 'handle']);

        // Mail logging — capture every sent and every failed mail job
        Event::listen(\Illuminate\Mail\Events\MessageSent::class, [LogMailSent::class, 'handle']);
        Event::listen(\Illuminate\Queue\Events\JobFailed::class, [LogMailFailed::class, 'handle']);

        // Session lifecycle emails
        Event::listen(SessionBooked::class, [SendSessionConfirmation::class, 'handle']);
        Event::listen(SessionCompleted::class, [SendSessionCompletionNotification::class, 'handle']);
        Event::listen(SessionCompleted::class, [\App\Listeners\CalculateTherapistCommission::class, 'handle']);
        Event::listen(\App\Events\SessionNoShow::class, [\App\Listeners\NotifyAdminOfSessionIssues::class, 'handle']);
        Event::listen(\App\Events\SessionEndedEarly::class, [\App\Listeners\NotifyAdminOfSessionIssues::class, 'handle']);
    }
}
