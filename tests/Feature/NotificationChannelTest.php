<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\BaseNotification;
use App\Notifications\DistressFlagRaised;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ARCH-7: Tests for BaseNotification.$mandatory flag.
 *
 * Verifies that:
 * - Mandatory notifications always deliver via mandatoryChannels regardless of prefs
 * - Non-mandatory notifications respect user notification preferences
 * - DistressFlagRaised.mandatory = true is enforced
 */
class NotificationChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_mandatory_notification_bypasses_user_preferences(): void
    {
        $user = User::factory()->create();

        // Create a notification setting that disables all channels
        $user->notificationSetting()->create([
            'email_notifications' => false,
            'push_notifications' => false,
        ]);

        $notification = new DistressFlagRaised(
            $user,
            'Test crisis message',
            'high'
        );

        $channels = $notification->via($user);

        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_distress_flag_raised_is_mandatory(): void
    {
        $user = User::factory()->create();

        $notification = new DistressFlagRaised(
            $user,
            'Crisis detected',
            'severe'
        );

        $reflection = new \ReflectionClass($notification);
        $mandatory = $reflection->getProperty('mandatory');
        $mandatory->setAccessible(true);

        $this->assertTrue($mandatory->getValue($notification));
    }

    public function test_standard_notification_respects_preferences_disabled(): void
    {
        $user = User::factory()->create();
        $user->notificationSetting()->create([
            'email_notifications' => false,
            'push_notifications' => false,
            'appointment_reminders' => false,
        ]);

        $notification = new \App\Notifications\SessionReminder(
            \App\Models\TherapySession::factory()->make(),
            '24 hours'
        );

        $channels = $notification->via($user);

        $this->assertEquals(['database'], $channels);
        $this->assertNotContains('mail', $channels);
    }

    public function test_standard_notification_includes_email_when_enabled(): void
    {
        $user = User::factory()->create();
        $user->notificationSetting()->create([
            'email_notifications' => true,
            'push_notifications' => false,
            'appointment_reminders' => true,
        ]);

        $notification = new \App\Notifications\SessionReminder(
            \App\Models\TherapySession::factory()->make(),
            '1 hour'
        );

        $channels = $notification->via($user);

        $this->assertContains('database', $channels);
        $this->assertContains('email', $channels);
        $this->assertNotContains('fcm', $channels);
    }
}
