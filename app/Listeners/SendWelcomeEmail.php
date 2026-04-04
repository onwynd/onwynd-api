<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Mail\FoundersWelcome;
use App\Mail\WelcomeEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UserRegistered $event): void
    {
        try {
            $user = $event->user;

            // Send Standard System Welcome
            Mail::to($user->email)->send(new WelcomeEmail(
                $user->name,
                rtrim(env('FRONTEND_URL', Config::get('app.url')), '/').'/login'
            ));

            // Send Founder's Personal Welcome (delayed slightly if possible, but here we queue it)
            // Delay by 15 minutes to avoid stacking emails immediately on signup.
            Mail::to($user->email)
                ->later(now()->addMinutes(15), new FoundersWelcome($user->name));

            Log::info('Welcome emails queued for user: '.$user->id);

        } catch (\Exception $e) {
            Log::error('Failed to send welcome emails: '.$e->getMessage());
        }
    }
}
