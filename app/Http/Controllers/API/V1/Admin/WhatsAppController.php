<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin endpoints for managing the WhatsApp channel.
 *
 * GET  /api/v1/admin/whatsapp/status      — connection state (both providers)
 * GET  /api/v1/admin/whatsapp/qr          — current QR code (qr provider only)
 * POST /api/v1/admin/whatsapp/disconnect  — log out linked device
 * PUT  /api/v1/admin/whatsapp/provider    — switch between 'qr' and 'termii'
 */
class WhatsAppController extends BaseController
{
    private WhatsAppService $wa;

    public function __construct()
    {
        $this->wa = new WhatsAppService();
    }

    /**
     * GET /api/v1/admin/whatsapp/status
     *
     * Returns the WhatsApp channel status:
     *  - provider:   'qr' | 'termii'
     *  - enabled:    whether WhatsApp is enabled globally
     *  - qr_status:  only when provider = 'qr'  ('connected'|'qr'|'disconnected'|'unreachable')
     *  - phone:      linked phone number (qr provider, when connected)
     */
    public function status(): JsonResponse
    {
        $settings = DB::table('settings')
            ->where('group', 'sms')
            ->whereIn('key', ['whatsapp_enabled', 'whatsapp_provider', 'whatsapp_phone_number_id'])
            ->pluck('value', 'key');

        $enabled  = ($settings['whatsapp_enabled']  ?? 'false') === 'true';
        $provider = $settings['whatsapp_provider']  ?? 'qr';

        $data = [
            'enabled'  => $enabled,
            'provider' => $provider,
        ];

        if ($provider === 'qr') {
            $qrStatus = $this->wa->getQrStatus();
            if ($qrStatus === null) {
                $data['qr_status'] = 'unreachable';
                $data['qr_hint']   = 'WhatsApp microservice is not running. Start it with: cd whatsapp-service && npm start';
            } else {
                $data['qr_status'] = $qrStatus['status'] ?? 'unknown';
                $data['phone']     = $qrStatus['phone']  ?? null;
                $data['push_name'] = $qrStatus['pushName'] ?? null;
                $data['has_qr']    = $qrStatus['hasQr']  ?? false;
            }
        } elseif ($provider === 'meta') {
            $metaStatus = $this->wa->getMetaStatus();
            $data = array_merge($data, $metaStatus);
            // Surface config presence without exposing the token
            $metaSettings = DB::table('settings')
                ->where('group', 'sms')
                ->whereIn('key', ['meta_wa_phone_number_id', 'meta_wa_access_token'])
                ->pluck('value', 'key');
            $data['phone_number_id_set'] = ! empty($metaSettings['meta_wa_phone_number_id'] ?? '');
            $data['access_token_set']    = ! empty($metaSettings['meta_wa_access_token']    ?? '');
        } else {
            // Termii — just show whether the API key is configured
            $apiKey = DB::table('settings')
                ->where('group', 'sms')->where('key', 'termii_api_key')
                ->value('value');
            $data['termii_configured'] = ! empty($apiKey);
            $data['phone_number_id']   = $settings['whatsapp_phone_number_id'] ?? '';
        }

        return $this->sendResponse($data, 'WhatsApp status retrieved.');
    }

    /**
     * GET /api/v1/admin/whatsapp/qr
     *
     * Returns the current QR code as a base64 data URI.
     * Only available when provider = 'qr' and status = 'qr'.
     */
    public function qr(): JsonResponse
    {
        $provider = DB::table('settings')
            ->where('group', 'sms')->where('key', 'whatsapp_provider')
            ->value('value') ?? 'qr';

        if ($provider !== 'qr') {
            return $this->sendError('QR code is only available with the QR-linked-device provider.', [], 400);
        }

        $result = $this->wa->getQrCode();

        if ($result === null) {
            return $this->sendError('WhatsApp microservice is unreachable or not in QR state.', [
                'hint' => 'Ensure the whatsapp-service is running: cd whatsapp-service && npm start',
            ], 503);
        }

        return $this->sendResponse($result, 'QR code retrieved.');
    }

    /**
     * POST /api/v1/admin/whatsapp/disconnect
     *
     * Logs out the linked device. Admin will need to scan a new QR code to reconnect.
     */
    public function disconnect(): JsonResponse
    {
        $provider = DB::table('settings')
            ->where('group', 'sms')->where('key', 'whatsapp_provider')
            ->value('value') ?? 'qr';

        if ($provider !== 'qr') {
            return $this->sendError('Disconnect is only applicable to the QR linked-device provider. For Meta or Termii, revoke credentials in their respective dashboards.', [], 400);
        }

        $success = $this->wa->disconnect();

        if (! $success) {
            return $this->sendError('Failed to disconnect. Is the microservice running?', [], 503);
        }

        return $this->sendResponse([], 'WhatsApp disconnected. Scan a new QR code to reconnect.');
    }

    /**
     * PUT /api/v1/admin/whatsapp/provider
     *
     * Body: { "provider": "qr" | "termii" }
     * Switches the active WhatsApp delivery provider.
     */
    public function setProvider(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string|in:qr,meta,termii',
        ]);

        DB::table('settings')->updateOrInsert(
            ['group' => 'sms', 'key' => 'whatsapp_provider'],
            ['value' => $request->input('provider'), 'updated_at' => now()]
        );

        return $this->sendResponse(
            ['provider' => $request->input('provider')],
            'WhatsApp provider updated.'
        );
    }
}
