<?php

namespace App\Helpers;

/**
 * Validation Helper
 * Common validation methods
 */
class ValidationHelper
{
    /**
     * Validate email
     */
    public static function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate phone
     */
    public static function isValidPhone($phone)
    {
        // Nigerian phone format: 234XXXXXXXXXX or 0XXXXXXXXXX
        return preg_match('/^(234|\+234|0)[7-9][0-1]\d{8}$/', str_replace(' ', '', $phone));
    }

    /**
     * Validate password strength
     */
    public static function isStrongPassword($password)
    {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password);
    }

    /**
     * Validate URL
     */
    public static function isValidUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate JSON
     */
    public static function isValidJson($json)
    {
        json_decode($json);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Validate amount
     */
    public static function isValidAmount($amount, $min = 0.01, $max = null)
    {
        if (! is_numeric($amount)) {
            return false;
        }

        $amount = (float) $amount;

        if ($amount < $min) {
            return false;
        }

        if ($max && $amount > $max) {
            return false;
        }

        return true;
    }

    /**
     * Validate date format
     */
    public static function isValidDateFormat($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);

        return $d && $d->format($format) === $date;
    }

    /**
     * Validate time format
     */
    public static function isValidTimeFormat($time, $format = 'H:i')
    {
        $t = \DateTime::createFromFormat($format, $time);

        return $t && $t->format($format) === $time;
    }
}
