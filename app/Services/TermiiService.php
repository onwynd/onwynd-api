<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Termii SMS / WhatsApp service.
 *
 * Used when the admin has set `whatsapp_provider = termii` (paid, scalable).
 * SMS always uses Termii; WhatsApp only uses Termii when the provider is set
 * to `termii`.
 *
 * Docs: https://developers.termii.com
 */
class TermiiService
{
    private string $apiKey;
    private string $senderId;
    private string $baseUrl = 'https://api.ng.termii.com/api';

    public function __construct(string $apiKey = '', string $senderId = 'ONWYND')
    {
        $this->apiKey   = $apiKey ?: config('services.termii.api_key', '');
        $this->senderId = $senderId ?: config('services.termii.sender_id', 'ONWYND');
    }

    // ── SMS ──────────────────────────────────────────────────────────────────

    /**
     * Send a plain-text SMS to a single recipient.
     *
     * @param  string $to      International format (e.g. "2348012345678")
     * @param  string $message Free-form text
     */
    public function sendSms(string $to, string $message): array
    {
        if (empty($this->apiKey)) {
            Log::warning('TermiiService: API key not set, skipping SMS to '.$to);
            return ['success' => false, 'reason' => 'no_api_key'];
        }

        try {
            $response = Http::post("{$this->baseUrl}/sms/send", [
                'to'       => $this->formatNumber($to),
                'from'     => $this->senderId,
                'sms'      => $message,
                'type'     => 'plain',
                'channel'  => 'generic',
                'api_key'  => $this->apiKey,
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['message_id'])) {
                return ['success' => true, 'message_id' => $data['message_id']];
            }

            Log::error('TermiiService SMS error', ['response' => $data, 'to' => $to]);
            return ['success' => false, 'reason' => $data['message'] ?? 'unknown'];
        } catch (\Exception $e) {
            Log::error('TermiiService SMS exception: '.$e->getMessage(), ['to' => $to]);
            return ['success' => false, 'reason' => $e->getMessage()];
        }
    }

    /**
     * Send an OTP via Termii's token API.
     * Returns ['success' => bool, 'pin_id' => string].
     */
    public function sendOtp(string $to, string $appName, int $length = 6, int $expiryMins = 10): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'reason' => 'no_api_key'];
        }

        try {
            $response = Http::post("{$this->baseUrl}/sms/otp/send", [
                'api_key'        => $this->apiKey,
                'message_type'   => 'NUMERIC',
                'to'             => $this->formatNumber($to),
                'from'           => $this->senderId,
                'channel'        => 'generic',
                'pin_attempts'   => 3,
                'pin_time_to_live' => $expiryMins,
                'pin_length'     => $length,
                'pin_placeholder'=> '< 1234 >',
                'message_text'   => "Your {$appName} verification code is < 1234 >. Valid for {$expiryMins} minutes.",
                'pin_type'       => 'NUMERIC',
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['pinId'])) {
                return ['success' => true, 'pin_id' => $data['pinId']];
            }

            Log::error('TermiiService OTP error', ['response' => $data]);
            return ['success' => false, 'reason' => $data['message'] ?? 'unknown'];
        } catch (\Exception $e) {
            Log::error('TermiiService OTP exception: '.$e->getMessage());
            return ['success' => false, 'reason' => $e->getMessage()];
        }
    }

    // ── WhatsApp ─────────────────────────────────────────────────────────────

    /**
     * Send a WhatsApp message via Termii's WhatsApp channel.
     * Only available when `whatsapp_provider = termii`.
     */
    public function sendWhatsApp(string $to, string $message): array
    {
        if (empty($this->apiKey)) {
            Log::warning('TermiiService: API key not set, skipping WhatsApp to '.$to);
            return ['success' => false, 'reason' => 'no_api_key'];
        }

        try {
            $response = Http::post("{$this->baseUrl}/sms/send", [
                'to'      => $this->formatNumber($to),
                'from'    => $this->senderId,
                'sms'     => $message,
                'type'    => 'plain',
                'channel' => 'whatsapp',
                'api_key' => $this->apiKey,
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['message_id'])) {
                return ['success' => true, 'message_id' => $data['message_id']];
            }

            Log::error('TermiiService WhatsApp error', ['response' => $data, 'to' => $to]);
            return ['success' => false, 'reason' => $data['message'] ?? 'unknown'];
        } catch (\Exception $e) {
            Log::error('TermiiService WhatsApp exception: '.$e->getMessage(), ['to' => $to]);
            return ['success' => false, 'reason' => $e->getMessage()];
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '234'.substr($phone, 1);
        } elseif (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }
        return $phone;
    }
}
