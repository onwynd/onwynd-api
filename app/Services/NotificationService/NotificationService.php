<?php

namespace App\Services\NotificationService;

use App\Models\Notification as NotificationModel;
use App\Models\User;
use App\Services\TermiiService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Central notification dispatcher.
 *
 * Handles in-app, SMS, and WhatsApp notifications. All channel availability
 * checks are driven by the `sms` settings group in the database:
 *
 *  sms_enabled            — global SMS master switch
 *  termii_api_key         — Termii API key (empty = SMS disabled)
 *  termii_sender_id       — SMS sender ID
 *  event_*                — per-event SMS toggles
 *  whatsapp_enabled       — global WhatsApp master switch
 *  whatsapp_provider      — 'qr' | 'termii'
 *  wa_event_*             — per-event WhatsApp toggles
 *
 * Templates are stored in the `sms_templates` group; use {placeholder} tokens.
 * OTP always bypasses per-user opt-out.
 */
class NotificationService
{
    private bool   $smsEnabled     = false;
    private bool   $waEnabled      = false;
    private string $apiKey         = '';
    private string $senderId       = 'ONWYND';
    private int    $otpExpiryMins  = 10;
    private int    $otpLength      = 6;
    private string $appName        = 'Onwynd';

    /** @var array<string,bool> Per-event SMS admin toggles keyed by event name */
    private array $eventToggles = [];
    /** @var array<string,bool> Per-event WhatsApp admin toggles keyed by event name */
    private array $waEventToggles = [];
    /** @var array<string,string> SMS/WA templates keyed by event name */
    private array $templates = [];

    private ?TermiiService $termii     = null;
    private ?WhatsAppService $whatsapp = null;

    public function __construct()
    {
        $this->appName = config('app.name', 'Onwynd');

        // Load all sms + sms_templates settings in two queries
        $smsSettings = DB::table('settings')
            ->where('group', 'sms')
            ->pluck('value', 'key');

        $this->smsEnabled    = ($smsSettings['sms_enabled']    ?? 'false') === 'true';
        $this->waEnabled     = ($smsSettings['whatsapp_enabled'] ?? 'false') === 'true';
        $this->apiKey        = $smsSettings['termii_api_key']  ?? '';
        $this->senderId      = $smsSettings['termii_sender_id'] ?? 'ONWYND';
        $this->otpExpiryMins = (int) ($smsSettings['otp_expiry_mins'] ?? 10);
        $this->otpLength     = (int) ($smsSettings['otp_length']      ?? 6);

        // Per-event SMS toggles
        $this->eventToggles = [
            'otp'                  => ($smsSettings['event_otp']                  ?? 'true') === 'true',
            'session_reminder'     => ($smsSettings['event_session_reminder']     ?? 'true') === 'true',
            'appointment'          => ($smsSettings['event_appointment']          ?? 'true') === 'true',
            'group_reminder'       => ($smsSettings['event_group_reminder']       ?? 'false') === 'true',
            'payment_confirmation' => ($smsSettings['event_payment_confirmation'] ?? 'false') === 'true',
        ];

        // Per-event WhatsApp toggles
        $this->waEventToggles = [
            'session_reminder'     => ($smsSettings['wa_event_session_reminder']     ?? 'false') === 'true',
            'appointment'          => ($smsSettings['wa_event_appointment']          ?? 'false') === 'true',
            'group_reminder'       => ($smsSettings['wa_event_group_reminder']       ?? 'false') === 'true',
            'payment_confirmation' => ($smsSettings['wa_event_payment_confirmation'] ?? 'false') === 'true',
        ];

        // SMS templates
        $this->templates = DB::table('settings')
            ->where('group', 'sms_templates')
            ->pluck('value', 'key')
            ->all();

        // Services — lazy-initialised only when needed
        if ($this->smsEnabled && $this->apiKey) {
            $this->termii = new TermiiService($this->apiKey, $this->senderId);
        }
        if ($this->waEnabled) {
            $this->whatsapp = new WhatsAppService();
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Public send methods
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * In-app welcome notification.
     */
    public function sendWelcomeNotification(User $user): void
    {
        try {
            $alreadySent = NotificationModel::where('user_id', $user->id)
                ->where('type', \App\Notifications\WelcomeNotification::class)
                ->exists();

            if ($alreadySent) {
                Log::info('Skipping duplicate welcome notification', ['user_id' => $user->id]);
                return;
            }

            $user->notify(new \App\Notifications\WelcomeNotification);
        } catch (\Exception $e) {
            Log::error('Failed to send welcome notification', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send OTP via SMS.
     * OTP bypasses user opt-out — security-critical.
     */
    public function sendOtp(string $phone, string $code, ?string $recipientName = null): void
    {
        if (! $this->eventToggles['otp']) {
            return; // admin has disabled OTP SMS (unusual but respected)
        }

        if (! $this->termii) {
            Log::warning('NotificationService: SMS not available, cannot send OTP to '.$phone);
            return;
        }

        $message = $this->renderTemplate('otp', [
            'code'        => $code,
            'expiry_mins' => $this->otpExpiryMins,
            'app_name'    => $this->appName,
            'name'        => $recipientName ?? 'User',
        ]);

        $this->termii->sendSms($phone, $message);
    }

    /**
     * Session reminder (1:1 therapy session).
     *
     * @param  string      $phone
     * @param  array       $vars   e.g. ['therapist_name'=>'Dr. Jane','time'=>'3:00 PM','date'=>'Tuesday']
     * @param  int|null    $userId Used to check user opt-in for SMS
     */
    public function sendSessionReminder(string $phone, array $vars, ?int $userId = null): void
    {
        if ($this->canSendSms('session_reminder', $userId)) {
            $message = $this->renderTemplate('session_reminder', $vars);
            $this->termii?->sendSms($phone, $message);
        }

        if ($this->canSendWhatsApp('session_reminder', $userId)) {
            $message = $this->renderTemplate('session_reminder', $vars);
            $this->whatsapp?->send($phone, $message);
        }
    }

    /**
     * Appointment confirmation (booking confirmed).
     */
    public function sendAppointmentConfirmation(string $phone, array $vars, ?int $userId = null): void
    {
        if ($this->canSendSms('appointment', $userId)) {
            $message = $this->renderTemplate('appointment', $vars);
            $this->termii?->sendSms($phone, $message);
        }

        if ($this->canSendWhatsApp('appointment', $userId)) {
            $message = $this->renderTemplate('appointment', $vars);
            $this->whatsapp?->send($phone, $message);
        }
    }

    /**
     * Group session reminder.
     */
    public function sendGroupSessionReminder(string $phone, array $vars, ?int $userId = null): void
    {
        if ($this->canSendSms('group_reminder', $userId)) {
            $message = $this->renderTemplate('group_reminder', $vars);
            $this->termii?->sendSms($phone, $message);
        }

        if ($this->canSendWhatsApp('group_reminder', $userId)) {
            $message = $this->renderTemplate('group_reminder', $vars);
            $this->whatsapp?->send($phone, $message);
        }
    }

    /**
     * Payment confirmation.
     */
    public function sendPaymentConfirmation(string $phone, array $vars, ?int $userId = null): void
    {
        if ($this->canSendSms('payment_confirmation', $userId)) {
            $message = $this->renderTemplate('payment_confirmation', $vars);
            $this->termii?->sendSms($phone, $message);
        }

        if ($this->canSendWhatsApp('payment_confirmation', $userId)) {
            $message = $this->renderTemplate('payment_confirmation', $vars);
            $this->whatsapp?->send($phone, $message);
        }
    }

    // ── Legacy / in-app stubs (preserved for existing callers) ───────────────

    public function sendSessionCompletionNotification(User $user): void
    {
        try {
            Log::info('Sending session completion notification', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send session completion notification', ['error' => $e->getMessage()]);
        }
    }

    public function sendSessionCancellationNotification(User $patient, User $therapist): void
    {
        try {
            Log::info('Sending session cancellation notification', [
                'patient_id'   => $patient->id,
                'therapist_id' => $therapist->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send session cancellation notification', ['error' => $e->getMessage()]);
        }
    }

    public function sendPayoutNotification(User $therapist, $amount, $date): void
    {
        try {
            Log::info('Sending payout notification', ['therapist_id' => $therapist->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send payout notification', ['error' => $e->getMessage()]);
        }
    }

    public function send2FACode(User $user, string $code): void
    {
        if ($user->phone) {
            $this->sendOtp($user->phone, $code, $user->first_name ?? null);
        }
    }

    public function sendSubscriptionExpiryWarning($subscription): void
    {
        try {
            Log::info('Sending subscription expiry warning', ['subscription_id' => $subscription->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send subscription expiry warning', ['error' => $e->getMessage()]);
        }
    }

    public function sendSubscriptionRenewalConfirmation($subscription): void
    {
        try {
            Log::info('Sending subscription renewal confirmation', ['subscription_id' => $subscription->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send subscription renewal confirmation', ['error' => $e->getMessage()]);
        }
    }

    public function sendSubscriptionExpiredNotification($subscription): void
    {
        try {
            Log::info('Sending subscription expired notification', ['subscription_id' => $subscription->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send subscription expired notification', ['error' => $e->getMessage()]);
        }
    }

    public function sendSubscriptionResumedNotification($subscription): void
    {
        try {
            Log::info('Sending subscription resumed notification', ['subscription_id' => $subscription->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send subscription resumed notification', ['error' => $e->getMessage()]);
        }
    }

    public function sendTherapistAvailabilityNotification($therapist): void
    {
        try {
            Log::info('Sending therapist availability notification', ['therapist_id' => $therapist->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send therapist availability notification', ['error' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Channel availability helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Whether a given event can be sent via SMS to an optional user.
     * OTP always bypasses user opt-out.
     */
    public function canSendSms(string $event, ?int $userId = null): bool
    {
        if (! $this->smsEnabled || ! $this->termii) {
            return false;
        }
        if (! ($this->eventToggles[$event] ?? false)) {
            return false;
        }
        if ($event !== 'otp' && $userId) {
            $optIn = DB::table('notification_settings')
                ->where('user_id', $userId)
                ->value('sms_notifications');
            if ($optIn !== null && ! $optIn) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether a given event can be sent via WhatsApp to an optional user.
     */
    public function canSendWhatsApp(string $event, ?int $userId = null): bool
    {
        if (! $this->waEnabled || ! $this->whatsapp) {
            return false;
        }
        if (! ($this->waEventToggles[$event] ?? false)) {
            return false;
        }
        if ($userId) {
            $optIn = DB::table('notification_settings')
                ->where('user_id', $userId)
                ->value('whatsapp_notifications');
            if ($optIn !== null && ! $optIn) {
                return false;
            }
        }
        return true;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Template engine
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Render a DB-stored template by replacing {placeholder} tokens.
     * Falls back to a sensible default if the template isn't seeded yet.
     */
    public function renderTemplate(string $event, array $vars): string
    {
        $template = $this->templates[$event] ?? $this->defaultTemplate($event);

        foreach ($vars as $key => $value) {
            $template = str_replace('{'.$key.'}', (string) $value, $template);
        }

        return $template;
    }

    private function defaultTemplate(string $event): string
    {
        return match ($event) {
            'otp'                  => 'Your {app_name} verification code is {code}. Valid for {expiry_mins} minutes.',
            'session_reminder'     => 'Hi {name}, reminder: your session with {therapist_name} is on {date} at {time}. - {app_name}',
            'appointment'          => 'Hi {name}, your appointment with {therapist_name} on {date} at {time} is confirmed. - {app_name}',
            'group_reminder'       => 'Hi {name}, your group session "{group_name}" starts on {date} at {time}. - {app_name}',
            'payment_confirmation' => 'Hi {name}, payment of {amount} confirmed for {plan_name}. Thank you! - {app_name}',
            default                => '{app_name}: {message}',
        };
    }
}
