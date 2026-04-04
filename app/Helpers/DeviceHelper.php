<?php

namespace App\Helpers;

/**
 * Device Helper
 * Device and browser detection
 */
class DeviceHelper
{
    /**
     * Detect device type from user agent
     */
    public static function getDeviceType($userAgent = null)
    {
        if (! $userAgent) {
            $userAgent = request()->userAgent();
        }

        if (preg_match('/Mobile/', $userAgent)) {
            return 'Mobile';
        } elseif (preg_match('/Tablet|iPad/', $userAgent)) {
            return 'Tablet';
        }

        return 'Desktop';
    }

    /**
     * Detect browser from user agent
     */
    public static function getBrowser($userAgent = null)
    {
        if (! $userAgent) {
            $userAgent = request()->userAgent();
        }

        if (preg_match('/Chrome/', $userAgent)) {
            return 'Chrome';
        } elseif (preg_match('/Firefox/', $userAgent)) {
            return 'Firefox';
        } elseif (preg_match('/Safari/', $userAgent)) {
            return 'Safari';
        } elseif (preg_match('/Edge/', $userAgent)) {
            return 'Edge';
        } elseif (preg_match('/Opera/', $userAgent)) {
            return 'Opera';
        }

        return 'Unknown';
    }

    /**
     * Get OS from user agent
     */
    public static function getOS($userAgent = null)
    {
        if (! $userAgent) {
            $userAgent = request()->userAgent();
        }

        if (preg_match('/Windows/', $userAgent)) {
            return 'Windows';
        } elseif (preg_match('/Mac/', $userAgent)) {
            return 'MacOS';
        } elseif (preg_match('/Linux/', $userAgent)) {
            return 'Linux';
        } elseif (preg_match('/iPhone|iPad/', $userAgent)) {
            return 'iOS';
        } elseif (preg_match('/Android/', $userAgent)) {
            return 'Android';
        }

        return 'Unknown';
    }

    /**
     * Get IP address
     */
    public static function getIP()
    {
        if (! empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }

        return $_SERVER['REMOTE_ADDR'] ?? request()->ip();
    }
}
