<?php

namespace App\Helpers;

/**
 * Pure-PHP TOTP (RFC 6238) helper — no third-party packages required.
 * Generates secrets and 6-digit codes compatible with Google Authenticator,
 * Authy, Microsoft Authenticator, and any RFC 6238-compliant app.
 */
class TotpHelper
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const STEP          = 30;   // seconds per time window
    private const DIGITS        = 6;
    private const WINDOW        = 1;    // ± steps to tolerate clock drift

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Generate a new random Base32 TOTP secret (160-bit / 32 chars).
     */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /**
     * Build the otpauth:// provisioning URI recognised by all authenticator apps.
     */
    public static function getUri(string $secret, string $email, string $issuer): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($email);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            $label,
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::STEP
        );
    }

    /**
     * Verify a 6-digit TOTP code.  Accepts ±WINDOW time steps for clock drift.
     */
    public static function verify(string $secret, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        for ($step = -self::WINDOW; $step <= self::WINDOW; $step++) {
            if (hash_equals(self::compute($secret, $step), $code)) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private static function compute(string $secret, int $offset = 0): string
    {
        $key     = self::base32Decode($secret);
        $counter = (int) floor(time() / self::STEP) + $offset;
        $msg     = pack('J', $counter);          // big-endian 64-bit unsigned
        $hmac    = hash_hmac('sha1', $msg, $key, true);

        $idx  = ord($hmac[19]) & 0x0f;
        $code = (
            ((ord($hmac[$idx])     & 0x7f) << 24) |
            ((ord($hmac[$idx + 1]) & 0xff) << 16) |
            ((ord($hmac[$idx + 2]) & 0xff) <<  8) |
            ((ord($hmac[$idx + 3]) & 0xff))
        ) % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $data): string
    {
        $binary = '';
        foreach (str_split($data) as $byte) {
            $binary .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        // Pad binary to a multiple of 5
        $binary .= str_repeat('0', (5 - (strlen($binary) % 5)) % 5);

        $encoded = '';
        foreach (str_split($binary, 5) as $chunk) {
            $encoded .= self::BASE32_CHARS[bindec($chunk)];
        }

        return $encoded;
    }

    private static function base32Decode(string $data): string
    {
        $data   = strtoupper(rtrim($data, '='));
        $lookup = array_flip(str_split(self::BASE32_CHARS));

        $binary = '';
        foreach (str_split($data) as $char) {
            if (! isset($lookup[$char])) {
                continue;
            }
            $binary .= str_pad(decbin($lookup[$char]), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($binary, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
