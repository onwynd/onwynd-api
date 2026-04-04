<?php

namespace App\Http\Controllers\API\V1;

use App\Helpers\GatewaySettings;
use App\Http\Controllers\API\BaseController;
use App\Models\Setting;
use App\Services\PlatformSettingsService;
use Illuminate\Support\Facades\Cache;

class ConfigController extends BaseController
{
    /**
     * Get public application configuration and feature flags.
     * B4: includes platform_booking_fee_ngn  non-premium users pay this on top of therapist rate.
     * H13: includes referral_credit_ngn for dynamic display.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $config = Cache::remember('app_config', 300, function () {
            // O.6: Auth Method Settings
            $authMethods = Setting::where('group', 'auth')
                ->pluck('value', 'key')
                ->map(function ($value) {
                    $lower = strtolower((string) $value);

                    return in_array($lower, ['1', 'true', 'yes', 'on'], true);
                })
                ->toArray();

            $prefixedFlags = Setting::where('key', 'like', 'feature_%')
                ->pluck('value', 'key')
                ->mapWithKeys(function ($value, $key) {
                    $cleanKey = str_replace(['feature_', '_enabled'], '', $key);

                    return [$cleanKey => filter_var($value, FILTER_VALIDATE_BOOLEAN)];
                })
                ->toArray();

            $groupFlags = Setting::where('group', 'features')
                ->pluck('value', 'key')
                ->map(function ($value) {
                    if (is_bool($value)) {
                        return $value;
                    }
                    $lower = strtolower((string) $value);
                    if (in_array($lower, ['1', 'true', 'yes', 'on'], true)) {
                        return true;
                    }
                    if (in_array($lower, ['0', 'false', 'no', 'off'], true)) {
                        return false;
                    }

                    return (bool) $value;
                })
                ->toArray();

            $featureFlags = array_merge($prefixedFlags, $groupFlags);

            $defaultStun = [['urls' => 'stun:stun.l.google.com:19302']];
            $iceServers = $defaultStun;
            $turnUrl = env('WEBRTC_TURN_URL');
            $turnUser = env('WEBRTC_TURN_USERNAME');
            $turnPass = env('WEBRTC_TURN_PASSWORD');
            if ($turnUrl && $turnUser && $turnPass) {
                $iceServers[] = [
                    'urls' => $turnUrl,
                    'username' => $turnUser,
                    'credential' => $turnPass,
                ];
            }

            if (! array_key_exists('review_approval_required', $featureFlags)) {
                $featureFlags['review_approval_required'] = false;
            }

            // B4: Platform booking fee  non-premium users pay this ON TOP of therapist rate.
            $platformBookingFeeNgn = (float) (
                Setting::where('key', 'platform_booking_fee_ngn')->value('value')
                ?? env('PLATFORM_BOOKING_FEE_NGN', 1500)
            );

            // H13: Referral credit amount for frontend display
            $referralCreditNgn = (float) (
                Setting::where('key', 'referral_credit_ngn')->value('value')
                ?? env('REFERRAL_CREDIT_NGN', 2000)
            );

            // VAT rate (%) — set by admin in Financial Settings; 0 = VAT exempt
            $vatRate = (float) (
                Setting::where('group', 'financial')->where('key', 'vat_rate')->value('value')
                ?? env('VAT_RATE', 0)
            );

            // Support Provider Configuration
            $supportProvider = Setting::where('group', 'support')->where('key', 'provider')->value('value') ?? 'ai_internal';
            $supportConfig = [
                'provider' => $supportProvider,
            ];

            if ($supportProvider === 'chatwoot') {
                $supportConfig['chatwoot_url'] = Setting::where('group', 'support')->where('key', 'chatwoot_url')->value('value');
                $supportConfig['chatwoot_website_token'] = Setting::where('group', 'support')->where('key', 'chatwoot_website_token')->value('value');
            } elseif ($supportProvider === 'intercom') {
                $supportConfig['intercom_app_id'] = Setting::where('group', 'support')->where('key', 'intercom_app_id')->value('value');
            }

            // Web branding colours — admin-controlled, falls back to Onwynd defaults
            $webColorsJson = Setting::where('group', 'branding')->where('key', 'web_colors')->value('value');
            $webColorDefaults = [
                'forest'    => '#122420', 'healing'   => '#2A7A6A',
                'breath'    => '#3AA090', 'pale'      => '#8EC4BA',
                'clay'      => '#C4561A', 'warm_clay' => '#E07A45',
                'bone'      => '#F5F0E6', 'ink'       => '#0A0E0C',
            ];
            $webColors = $webColorsJson ? (json_decode($webColorsJson, true) ?: $webColorDefaults) : $webColorDefaults;

            $boolSetting = fn (string $key) => filter_var(
                Setting::where('group', 'features')->where('key', $key)->value('value') ?? 'false',
                FILTER_VALIDATE_BOOLEAN
            );

            // Geo / localisation flags (group = 'geo')
            $geoSetting = fn (string $key, $default = null) => Setting::where('group', 'geo')->where('key', $key)->value('value') ?? $default;
            $geoBool    = fn (string $key, bool $default = true) => filter_var($geoSetting($key, $default ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN);
            $geo = [
                'auto_detect'               => $geoBool('auto_detect'),
                'regional_testimonials'     => $geoBool('regional_testimonials'),
                'regional_pricing'          => $geoBool('regional_pricing'),
                'regional_phone'            => $geoBool('regional_phone'),
                'regional_payment_gateway'  => $geoBool('regional_payment_gateway'),
                'international_gateway'     => $geoSetting('international_gateway', 'dodopayments'),
                'stripe_paused'             => $geoBool('stripe_paused'),
            ];

            return [
                'features' => $featureFlags,
                'auth_methods' => $authMethods,
                'version' => '1.0.0',
                'maintenance_mode' => $boolSetting('maintenance_mode'),
                'waitlist_mode'    => $boolSetting('waitlist_mode'),
                'support_email' => config('onwynd.support_email', 'hello@onwynd.com'),
                'display_exchange_rate' => config('onwynd.display_exchange_rate', 1600),
                'support_config' => $supportConfig,
                'platform_booking_fee_ngn' => $platformBookingFeeNgn,
                'referral_credit_ngn' => $referralCreditNgn,
                'vat_rate' => $vatRate,
                'vat_enabled' => filter_var(PlatformSettingsService::get('vat_enabled', 'false'), FILTER_VALIDATE_BOOLEAN),
                'vat_label'   => PlatformSettingsService::get('vat_label', 'VAT (7.5%)'),
                'ngn_usd_rate' => (float) (Setting::where('group', 'financial')->where('key', 'ngn_usd_rate')->value('value') ?? env('NGN_USD_RATE', 1500)),
                'booking_fee_enabled'     => filter_var(PlatformSettingsService::get('booking_fee_enabled', 'true'), FILTER_VALIDATE_BOOLEAN),
                'booking_fee_ngn'         => (float) PlatformSettingsService::get('booking_fee_ngn', '100'),
                'booking_fee_usd'         => (float) PlatformSettingsService::get('booking_fee_usd', '0.10'),
                'free_consults_per_month' => (int) PlatformSettingsService::get('freemium_free_consults_per_month', '1'),
                'webrtc' => ['ice_servers' => $iceServers],
                'web_colors' => $webColors,
                'geo'      => $geo,
                'gateways' => GatewaySettings::publicSummary(),
            ];
        });

        return $this->sendResponse($config, 'Application configuration retrieved successfully.');
    }

    /**
     * GET /api/v1/config/ip-protection
     *
     * Returns current IP protection settings.
     * Cached for 5 minutes. Cache invalidated when settings change.
     * Public endpoint — no auth required.
     */
    public function ipProtection()
    {
        $config = Cache::remember('ip_protection_config', 300, function () {
            $keys = [
                'ip_protection_web_enabled',
                'ip_protect_web_devtools',
                'ip_protect_web_rightclick',
                'ip_protect_web_textselection',
                'ip_protect_web_keyboard',
                'ip_protect_web_dragging',
                'ip_protect_web_log_attempts',
                'ip_protection_dashboard_enabled',
                'ip_protect_dash_devtools',
                'ip_protect_dash_rightclick',
                'ip_protect_dash_textselection',
                'ip_protect_dash_keyboard',
                'ip_protect_dash_clipboard',
                'ip_protect_dash_log_attempts',
            ];

            $settings = Setting::whereIn('key', $keys)->get()
                ->keyBy('key')
                ->map(fn ($s) => $s->value === 'true' || $s->value === '1');

            return $settings->toArray();
        });

        return $this->sendResponse($config, 'IP protection configuration retrieved.');
    }
}
