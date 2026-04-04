<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Mail\FoundersWelcome;
use App\Models\User;
use App\Services\Admin\AdminNotificationService;
use App\Services\NotificationService\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendWelcomeNotification
{
    /**
     * @var NotificationService
     */
    private $notificationService;

    /**
     * Create the event listener
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event
     */
    public function handle(UserCreated $event): void
    {
        try {
            /** @var User $user */
            $user = $event->user;

            // Skip anonymous placeholder emails — no real inbox to deliver to
            if ($user->is_anonymous || str_ends_with($user->email, '@anonymous.onwynd.com')) {
                Log::info('Skipping welcome notification for anonymous user', ['user_id' => $user->id]);
                return;
            }

            Log::info('Sending welcome notification', ['user_id' => $user->id]);

            // Send in-app (database) welcome notification
            $this->notificationService->sendWelcomeNotification($user);

            // Notify admins of new signup (skip anonymous & internal)
            if (! $user->is_anonymous) {
                AdminNotificationService::newUserSignup(
                    trim("{$user->first_name} {$user->last_name}"),
                    $user->email,
                    $user->role ?? 'user'
                );
            }

            // Queue Founder's Personal Welcome — 30-min delay, idempotency lock prevents double-send
            $lockKey = 'founders_welcome_queued_user_'.$user->id;
            if (Cache::add($lockKey, true, now()->addDays(2))) {
                Mail::to($user->email)
                    ->later(now()->addMinutes(30), new FoundersWelcome($user->first_name.' '.$user->last_name));
                Log::info('Founder welcome queued', ['user_id' => $user->id]);
            } else {
                Log::info('Founder welcome already queued/skipped', ['user_id' => $user->id]);
            }

            // NOTE: WelcomeEmail is intentionally NOT sent here.
            // DispatchDripEmails (emails:dispatch-drip) is the single source of truth
            // for the welcome step — it records the send in user_email_sequences and
            // will deliver the email on day 0, preventing any race-condition double-send.

            Log::info('Welcome notification completed for user: '.$user->id);
        } catch (\Exception $e) {
            Log::error('Failed to send welcome notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
