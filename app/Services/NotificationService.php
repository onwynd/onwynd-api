<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Models\Setting;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $client;

    protected $apiKey;

    protected $senderId;

    protected $baseUrl = 'https://api.ng.termii.com/api/';

    protected bool $smsEnabled;

    /** Per-event admin toggles */
    protected array $eventToggles;

    /** DB-stored message templates keyed by event name */
    protected array $templates;

    /** App name for use in template variables */
    protected string $appName;

    /** OTP expiry in minutes */
    protected int $otpExpiryMins;

    public function __construct()
    {
        // Batch-load SMS config + templates in two queries
        $smsSettings = Setting::where('group', 'sms')->pluck('value', 'key');
        $rawTemplates = Setting::where('group', 'sms_templates')->pluck('value', 'key');

        $dbKey      = $smsSettings->get('termii_api_key', '');
        $dbSenderId = $smsSettings->get('termii_sender_id', '');
        $dbEnabled  = $smsSettings->get('sms_enabled');

        $this->apiKey        = ($dbKey !== '') ? $dbKey : config('services.termii.api_key', env('TERMII_API_KEY', ''));
        $this->senderId      = ($dbSenderId !== '') ? $dbSenderId : config('services.termii.sender_id', env('TERMII_SENDER_ID', 'ONWYND'));
        $this->smsEnabled    = $dbEnabled !== null ? \filter_var($dbEnabled, FILTER_VALIDATE_BOOLEAN) : true;
        $this->otpExpiryMins = (int) $smsSettings->get('otp_expiry_mins', 10);
        $this->appName       = config('app.name', 'Onwynd');

        $this->eventToggles = [
            'otp'              => \filter_var($smsSettings->get('event_otp', 'true'), FILTER_VALIDATE_BOOLEAN),
            'session_reminder' => \filter_var($smsSettings->get('event_session_reminder', 'true'), FILTER_VALIDATE_BOOLEAN),
            'appointment'      => \filter_var($smsSettings->get('event_appointment', 'true'), FILTER_VALIDATE_BOOLEAN),
            'group_reminder'   => \filter_var($smsSettings->get('event_group_reminder', 'false'), FILTER_VALIDATE_BOOLEAN),
            'payment_confirmation' => true, // always on when SMS is enabled
        ];

        // Default templates (fallback if DB has none)
        $defaultTemplates = [
            'otp'                  => "Your {app_name} verification code is {code}. Valid for {expiry_mins} minutes. Do not share this code.",
            'session_reminder'     => "Hi {name}, reminder: therapy session with {therapist_name} at {session_time}. Log in via {app_name}.",
            'appointment'          => "Hi {name}, appointment with {therapist_name} confirmed for {date} at {time}. — {app_name}.",
            'group_reminder'       => "Hi {name}, group session \"{session_title}\" starts at {time}. Join via {app_name} dashboard.",
            'payment_confirmation' => "Hi {name}, payment of {amount} {currency} for {plan_name} was successful. — {app_name}.",
        ];
        foreach ($defaultTemplates as $key => $default) {
            $this->templates[$key] = $rawTemplates->get($key, $default) ?: $default;
        }

        $this->client = new Client(['base_uri' => $this->baseUrl]);
    }

    /**
     * Return true if SMS is configured and enabled globally.
     */
    public function isAvailable(): bool
    {
        return $this->smsEnabled && ! empty($this->apiKey);
    }

    /**
     * Render a stored template for $event, replacing {placeholder} tokens.
     *
     * @param  string  $event    Template key (otp, session_reminder, …)
     * @param  array   $vars     Associative map of placeholder → value
     * @return string  Rendered message body
     */
    public function renderTemplate(string $event, array $vars = []): string
    {
        $template = $this->templates[$event] ?? '';
        $vars['app_name'] = $vars['app_name'] ?? $this->appName;

        foreach ($vars as $placeholder => $value) {
            $template = str_replace("{{$placeholder}}", (string) $value, $template);
        }

        return $template;
    }

    /**
     * Check whether an event type is enabled by admin AND the user has not opted out.
     *
     * @param  string  $event  otp | session_reminder | appointment | group_reminder
     * @param  int|null  $userId  If provided, also checks the user's sms_notifications preference
     */
    protected function canSend(string $event, ?int $userId = null): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        if (! ($this->eventToggles[$event] ?? false)) {
            return false;
        }

        // OTP is always sent regardless of user preference (security-critical)
        if ($event === 'otp') {
            return true;
        }

        if ($userId !== null) {
            $notifSetting = NotificationSetting::where('user_id', $userId)->first();
            if ($notifSetting && ! $notifSetting->isSMSNotificationsEnabled()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Send OTP via SMS — always fires when SMS is available (security-critical).
     */
    public function sendOTP($phone, $code)
    {
        if (! $this->canSend('otp')) {
            return ['code' => 'skipped', 'message' => 'SMS disabled or not configured'];
        }

        $message = $this->renderTemplate('otp', [
            'code'        => $code,
            'expiry_mins' => $this->otpExpiryMins,
        ]);

        try {
            $response = $this->client->post('sms/send', [
                'json' => [
                    'api_key' => $this->apiKey,
                    'to'      => $this->formatPhoneNumber($phone),
                    'from'    => $this->senderId,
                    'sms'     => $message,
                    'type'    => 'plain',
                    'channel' => 'generic',
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('Termii OTP Error: '.$e->getMessage());

            return ['code' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Send session reminder — gated by admin event_session_reminder toggle + user opt-in.
     */
    public function sendSessionReminder($phone, $therapistName, $sessionTime, ?int $userId = null, string $recipientName = '')
    {
        if (! $this->canSend('session_reminder', $userId)) {
            return ['code' => 'skipped'];
        }

        return $this->sendMessage($phone, $this->renderTemplate('session_reminder', [
            'name'           => $recipientName,
            'therapist_name' => $therapistName,
            'session_time'   => $sessionTime,
        ]));
    }

    /**
     * Send appointment confirmation — gated by admin event_appointment toggle + user opt-in.
     */
    public function sendAppointmentConfirmation($phone, $therapistName, $date, $time, ?int $userId = null, string $recipientName = '')
    {
        if (! $this->canSend('appointment', $userId)) {
            return ['code' => 'skipped'];
        }

        return $this->sendMessage($phone, $this->renderTemplate('appointment', [
            'name'           => $recipientName,
            'therapist_name' => $therapistName,
            'date'           => $date,
            'time'           => $time,
        ]));
    }

    /**
     * Send group session reminder — gated by admin event_group_reminder toggle + user opt-in.
     */
    public function sendGroupSessionReminder($phone, $title, $time, $isTherapist = false, ?int $userId = null, string $recipientName = '')
    {
        if (! $this->canSend('group_reminder', $userId)) {
            return ['code' => 'skipped'];
        }

        $message = $this->renderTemplate('group_reminder', [
            'name'          => $recipientName,
            'session_title' => $title,
            'time'          => $time,
        ]);

        if ($isTherapist) {
            // L.3: Prioritize WhatsApp for therapists
            return $this->sendWhatsAppMessage($phone, $message);
        }

        return $this->sendMessage($phone, $message);
    }

    /**
     * Send payment confirmation SMS.
     */
    public function sendPaymentConfirmation($phone, string $recipientName, string $amount, string $currency, string $planName, ?int $userId = null)
    {
        if (! $this->canSend('payment_confirmation', $userId)) {
            return ['code' => 'skipped'];
        }

        return $this->sendMessage($phone, $this->renderTemplate('payment_confirmation', [
            'name'      => $recipientName,
            'amount'    => $amount,
            'currency'  => $currency,
            'plan_name' => $planName,
        ]));
    }

    /**
     * Generic Send Message
     */
    public function sendMessage($phone, $message)
    {
        try {
            $response = $this->client->post('sms/send', [
                'json' => [
                    'api_key' => $this->apiKey,
                    'to' => $this->formatPhoneNumber($phone),
                    'from' => $this->senderId,
                    'sms' => $message,
                    'type' => 'plain',
                    'channel' => 'generic',
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('Termii SMS Error: '.$e->getMessage());

            return ['code' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Send WhatsApp message
     */
    public function sendWhatsAppMessage($phone, $message)
    {
        try {
            // Using Termii WhatsApp API
            $response = $this->client->post('whatsapp/send', [
                'json' => [
                    'api_key' => $this->apiKey,
                    'to' => $this->formatPhoneNumber($phone),
                    'from' => config('services.whatsapp.phone_number_id'),
                    'sms' => $message,
                    'type' => 'plain',
                    'channel' => 'whatsapp',
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('WhatsApp Error: '.$e->getMessage());

            // Fallback to SMS
            return $this->sendMessage($phone, $message);
        }
    }

    protected function formatPhoneNumber($phone)
    {
        // Ensure format is international without + (e.g., 23480...)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '234'.substr($phone, 1);
        }

        return $phone;
    }
}
