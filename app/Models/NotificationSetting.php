<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NotificationSetting Model
 *
 * Stores user notification preferences
 * Replaces the placeholder settings approach in UserAccountController
 *
 * @property int $id
 * @property int $user_id
 * @property bool $email_notifications Receive email notifications
 * @property bool $sms_notifications Receive SMS notifications
 * @property bool $session_reminders Session reminder notifications
 * @property bool $message_notifications In-app message notifications
 * @property bool $billing_notifications Billing-related notifications
 * @property bool $promotional_emails Marketing and promotional emails
 * @property bool $newsletter Subscribe to newsletter
 * @property bool $community_updates Community activity notifications
 * @property bool $appointment_reminders Therapy appointment reminders
 * @property string $email_frequency Daily, weekly, or never
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class NotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email_notifications',
        'sms_notifications',
        'push_notifications',
        'whatsapp_notifications',
        'session_reminders',
        'message_notifications',
        'billing_notifications',
        'promotional_emails',
        'newsletter',
        'community_updates',
        'appointment_reminders',
        'wellbeing_checkins',
        'platform_updates',
        'channel_preferences',
    ];

    protected $casts = [
        'email_notifications' => 'boolean',
        'sms_notifications' => 'boolean',
        'push_notifications' => 'boolean',
        'whatsapp_notifications' => 'boolean',
        'session_reminders' => 'boolean',
        'message_notifications' => 'boolean',
        'billing_notifications' => 'boolean',
        'promotional_emails' => 'boolean',
        'newsletter' => 'boolean',
        'community_updates' => 'boolean',
        'appointment_reminders' => 'boolean',
        'wellbeing_checkins' => 'boolean',
        'platform_updates' => 'boolean',
        'channel_preferences' => 'array',
    ];

    protected $table = 'notification_settings';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeEmailEnabled($query)
    {
        return $query->where('email_notifications', true);
    }

    public function scopeSMSEnabled($query)
    {
        return $query->where('sms_notifications', true);
    }

    public function scopeNewsletterSubscribers($query)
    {
        return $query->where('newsletter', true);
    }

    // Helper methods
    public function isEmailNotificationsEnabled(): bool
    {
        return $this->email_notifications ?? true;
    }

    public function isSMSNotificationsEnabled(): bool
    {
        return $this->sms_notifications ?? false;
    }

    public function shouldSendSessionReminder(): bool
    {
        return $this->session_reminders ?? true;
    }

    public function shouldSendAppointmentReminder(): bool
    {
        return $this->appointment_reminders ?? true;
    }

    public function shouldReceiveMarketing(): bool
    {
        return $this->promotional_emails ?? false || $this->newsletter ?? false;
    }

    public function getActiveChannels(): array
    {
        $channels = [];

        if ($this->email_notifications) {
            $channels[] = 'email';
        }

        if ($this->sms_notifications) {
            $channels[] = 'sms';
        }

        if ($this->message_notifications) {
            $channels[] = 'in_app';
        }

        return $channels;
    }

    public static function getDefaults(): array
    {
        return [
            'email_notifications' => true,
            'sms_notifications' => false,
            'session_reminders' => true,
            'message_notifications' => true,
            'billing_notifications' => true,
            'promotional_emails' => false,
            'newsletter' => false,
            'community_updates' => true,
            'appointment_reminders' => true,
            'email_frequency' => 'daily',
        ];
    }
}
