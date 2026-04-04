<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Reads and writes platform-level settings stored in the `settings` table
 * under group='platform'. All reads are cache-backed (60-minute TTL).
 */
class PlatformSettingsService
{
    private const CACHE_TTL = 3600; // 60 minutes
    private const GROUP = 'platform';

    /**
     * Read a platform setting by key.
     * Returns $default when the key does not exist.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember(
            "platform_setting:{$key}",
            self::CACHE_TTL,
            fn () => Setting::where('group', self::GROUP)
                ->where('key', $key)
                ->value('value') ?? $default
        );
    }

    /**
     * Write a platform setting and clear its cache entry.
     */
    public static function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(
            ['group' => self::GROUP, 'key' => $key],
            ['value' => (string) $value, 'type' => 'string']
        );

        Cache::forget("platform_setting:{$key}");

        Log::info('PlatformSettingsService: setting updated', [
            'key' => $key,
            'value' => $value,
        ]);
    }
}
