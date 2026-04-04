<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp dispatcher.
 *
 * Three providers — all controlled by `whatsapp_provider` in the `sms` settings group:
 *
 *   - `qr`     (default / free)  — whatsapp-web.js microservice; admin scans QR once.
 *   - `meta`   (official / paid) — Meta / Facebook Cloud API (WhatsApp Business Platform).
 *                                   Needs a verified Business Account + approved templates for
 *                                   session-initiated messages outside the 24h window.
 *   - `termii` (scale / managed) — Termii's WhatsApp Business channel; same Termii API key.
 */
class WhatsAppService
{
    private string $provider;

    // QR microservice
    private string $microserviceUrl;
    private string $microserviceSecret;

    // Meta / Facebook Cloud API
    private string $metaPhoneNumberId;
    private string $metaAccessToken;
    private string $metaApiVersion = 'v19.0';

    // Termii
    private TermiiService $termii;

    public function __construct()
    {
        $settings = DB::table('settings')
            ->where('group', 'sms')
            ->whereIn('key', [
                'whatsapp_provider',
                'termii_api_key', 'termii_sender_id',
                'meta_wa_phone_number_id', 'meta_wa_access_token',
            ])
            ->pluck('value', 'key');

        $this->provider = $settings['whatsapp_provider'] ?? 'qr';

        // QR microservice config
        $this->microserviceUrl    = config('services.whatsapp_microservice.url', 'http://localhost:3001');
        $this->microserviceSecret = config('services.whatsapp_microservice.secret', '');

        // Meta config — DB first, then .env fallback
        $this->metaPhoneNumberId = $settings['meta_wa_phone_number_id']
            ?? config('services.whatsapp.phone_number_id', '');
        $this->metaAccessToken   = $settings['meta_wa_access_token']
            ?? config('services.whatsapp.access_token', '');

        // Termii
        $this->termii = new TermiiService(
            $settings['termii_api_key']   ?? '',
            $settings['termii_sender_id'] ?? 'ONWYND'
        );
    }

    /**
     * Send a WhatsApp message to a phone number.
     * Routes to the configured provider automatically.
     */
    public function send(string $to, string $message): array
    {
        return match ($this->provider) {
            'meta'   => $this->sendViaMeta($to, $message),
            'termii' => $this->termii->sendWhatsApp($to, $message),
            default  => $this->sendViaQrMicroservice($to, $message),
        };
    }

    /**
     * Test the Meta connection by fetching the phone number profile.
     * Returns ['connected' => bool, 'display_name' => string, ...].
     */
    public function getMetaStatus(): array
    {
        if (empty($this->metaPhoneNumberId) || empty($this->metaAccessToken)) {
            return ['connected' => false, 'reason' => 'credentials_missing'];
        }

        try {
            $response = Http::timeout(8)
                ->withToken($this->metaAccessToken)
                ->get("https://graph.facebook.com/{$this->metaApiVersion}/{$this->metaPhoneNumberId}",
                    ['fields' => 'display_phone_number,verified_name,quality_rating']);

            $data = $response->json();

            if (isset($data['error'])) {
                return ['connected' => false, 'reason' => $data['error']['message'] ?? 'api_error'];
            }

            return [
                'connected'    => true,
                'phone'        => $data['display_phone_number'] ?? null,
                'display_name' => $data['verified_name']        ?? null,
                'quality'      => $data['quality_rating']       ?? null,
            ];
        } catch (\Exception $e) {
            return ['connected' => false, 'reason' => $e->getMessage()];
        }
    }

    /**
     * Get the current connection status from the QR microservice.
     * Returns ['status' => 'connected'|'qr'|'disconnected'|..., 'phone' => ?, 'hasQr' => bool]
     * or null if the microservice is unreachable.
     */
    public function getQrStatus(): ?array
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders($this->headers())
                ->get("{$this->microserviceUrl}/status");

            return $response->json();
        } catch (\Exception $e) {
            Log::warning('WhatsAppService: QR microservice unreachable — '.$e->getMessage());
            return null;
        }
    }

    /**
     * Fetch the current QR code from the microservice.
     * Returns ['qr' => 'data:image/png;base64,...'] or null when not in QR state.
     */
    public function getQrCode(): ?array
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders($this->headers())
                ->get("{$this->microserviceUrl}/qr");

            if ($response->successful()) {
                return $response->json();
            }
            return null;
        } catch (\Exception $e) {
            Log::warning('WhatsAppService: could not fetch QR code — '.$e->getMessage());
            return null;
        }
    }

    /**
     * Disconnect the QR microservice session.
     */
    public function disconnect(): bool
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders($this->headers())
                ->post("{$this->microserviceUrl}/disconnect");

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('WhatsAppService: disconnect failed — '.$e->getMessage());
            return false;
        }
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function sendViaMeta(string $to, string $message): array
    {
        if (empty($this->metaPhoneNumberId) || empty($this->metaAccessToken)) {
            Log::warning('WhatsAppService: Meta credentials not configured');
            return ['success' => false, 'reason' => 'credentials_missing'];
        }

        $phone = $this->formatNumber($to);

        try {
            $response = Http::timeout(15)
                ->withToken($this->metaAccessToken)
                ->post(
                    "https://graph.facebook.com/{$this->metaApiVersion}/{$this->metaPhoneNumberId}/messages",
                    [
                        'messaging_product' => 'whatsapp',
                        'to'                => $phone,
                        'type'              => 'text',
                        'text'              => ['body' => $message],
                    ]
                );

            $data = $response->json();

            if ($response->successful() && isset($data['messages'][0]['id'])) {
                return ['success' => true, 'message_id' => $data['messages'][0]['id']];
            }

            Log::error('WhatsAppService Meta send failed', ['response' => $data, 'to' => $to]);
            return ['success' => false, 'reason' => $data['error']['message'] ?? 'unknown'];
        } catch (\Exception $e) {
            Log::error('WhatsAppService Meta exception: '.$e->getMessage(), ['to' => $to]);
            return ['success' => false, 'reason' => $e->getMessage()];
        }
    }

    private function sendViaQrMicroservice(string $to, string $message): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders($this->headers())
                ->post("{$this->microserviceUrl}/send", [
                    'to'      => $to,
                    'message' => $message,
                ]);

            $data = $response->json();

            if ($response->successful() && ($data['success'] ?? false)) {
                return ['success' => true, 'to' => $data['to'] ?? $to];
            }

            Log::error('WhatsAppService QR send failed', ['response' => $data, 'to' => $to]);
            return ['success' => false, 'reason' => $data['error'] ?? 'unknown'];
        } catch (\Exception $e) {
            Log::error('WhatsAppService QR exception: '.$e->getMessage(), ['to' => $to]);
            return ['success' => false, 'reason' => $e->getMessage()];
        }
    }

    private function headers(): array
    {
        $h = ['Accept' => 'application/json'];
        if ($this->microserviceSecret) {
            $h['X-Api-Secret'] = $this->microserviceSecret;
        }
        return $h;
    }

    private function formatNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '234'.substr($phone, 1);
        }
        return $phone;
    }
}
