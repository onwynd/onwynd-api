<?php

namespace App\Helpers;

use App\Models\Setting;

/**
 * Centralised helper for reading payment-gateway settings from the database.
 *
 * Storage convention (group = 'gateways'):
 *   {name}_enabled           — '1'|'true' or '0'|'false'
 *   {name}_mode              — 'test' or 'live'
 *   {name}_{mode}_public_key
 *   {name}_{mode}_secret_key
 *
 * Supported gateways: paystack, flutterwave, klump, stripe, dodopayments
 */
class GatewaySettings
{
    /** All known gateways with their currency and type metadata */
    public const GATEWAYS = [
        'paystack'     => ['currency' => 'NGN', 'type' => 'standard'],
        'flutterwave'  => ['currency' => 'NGN', 'type' => 'standard'],
        'klump'        => ['currency' => 'NGN', 'type' => 'bnpl'],
        'stripe'       => ['currency' => 'USD', 'type' => 'standard'],
        'dodopayments' => ['currency' => 'USD', 'type' => 'standard'],
    ];

    /** Gateways enabled by default when no DB record exists */
    private const DEFAULTS_ENABLED = ['paystack' => true, 'dodopayments' => true];

    /**
     * Get all resolved fields for a single gateway.
     *
     * @return array{enabled: bool, mode: string, public_key: string, secret_key: string, currency: string, type: string}
     */
    public static function gateway(string $name): array
    {
        $defaultEnabled = static::DEFAULTS_ENABLED[$name] ?? false;
        $rawEnabled = Setting::where('group', 'gateways')->where('key', "{$name}_enabled")->value('value');
        $enabled    = $rawEnabled !== null ? \filter_var($rawEnabled, FILTER_VALIDATE_BOOLEAN) : $defaultEnabled;

        $mode      = Setting::where('group', 'gateways')->where('key', "{$name}_mode")->value('value') ?? 'test';
        $publicKey = Setting::where('group', 'gateways')->where('key', "{$name}_{$mode}_public_key")->value('value') ?? '';
        $secretKey = Setting::where('group', 'gateways')->where('key', "{$name}_{$mode}_secret_key")->value('value') ?? '';

        return [
            'enabled'    => $enabled,
            'mode'       => $mode,
            'public_key' => $publicKey,
            'secret_key' => $secretKey,
            'currency'   => static::GATEWAYS[$name]['currency'] ?? 'NGN',
            'type'       => static::GATEWAYS[$name]['type'] ?? 'standard',
        ];
    }

    /**
     * Return only the effective secret key, falling back to $fallback if empty.
     */
    public static function secretKey(string $name, string $fallback = ''): string
    {
        $key = static::gateway($name)['secret_key'];

        return ($key !== '') ? $key : $fallback;
    }

    /**
     * Return whether the gateway is enabled.
     */
    public static function enabled(string $name): bool
    {
        return static::gateway($name)['enabled'];
    }

    /**
     * Return current mode ('test' or 'live').
     */
    public static function mode(string $name): string
    {
        return Setting::where('group', 'gateways')->where('key', "{$name}_mode")->value('value') ?? 'test';
    }

    /**
     * Return public-safe summary of all gateways (enabled + mode only, no keys).
     */
    public static function publicSummary(): array
    {
        $out = [];
        foreach (array_keys(static::GATEWAYS) as $name) {
            $gw = static::gateway($name);
            $out[$name] = [
                'enabled'  => $gw['enabled'],
                'mode'     => $gw['mode'],
                'currency' => $gw['currency'],
                'type'     => $gw['type'],
            ];
        }

        return $out;
    }
}
