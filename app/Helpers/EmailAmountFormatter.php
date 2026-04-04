<?php

namespace App\Helpers;

use App\Services\PlatformSettingsService;

class EmailAmountFormatter
{
    public static function formatTotal(
        float $sessionFee,
        float $bookingFee = 0.0,
        float $promoDiscount = 0.0,
        string $currency = 'NGN'
    ): array {
        $symbol     = $currency === 'USD' ? '$' : '₦';
        $vatEnabled = filter_var(PlatformSettingsService::get('vat_enabled', 'false'), FILTER_VALIDATE_BOOLEAN);
        $vatRate    = (float) PlatformSettingsService::get('vat_rate', '0.075');
        $subtotal   = max(0, $sessionFee - $promoDiscount);
        $vatAmount  = $vatEnabled ? round($subtotal * $vatRate, 2) : 0.0;
        $total      = $subtotal + $bookingFee + $vatAmount;

        return [
            'session_fee'    => $symbol . number_format($sessionFee, 2),
            'promo_discount' => $promoDiscount > 0 ? '-' . $symbol . number_format($promoDiscount, 2) : null,
            'subtotal'       => $symbol . number_format($subtotal, 2),
            'booking_fee'    => $bookingFee > 0 ? $symbol . number_format($bookingFee, 2) : null,
            'vat_label'      => $vatEnabled ? PlatformSettingsService::get('vat_label', 'VAT (7.5%)') : null,
            'vat_line'       => $vatEnabled ? $symbol . number_format($vatAmount, 2) : null,
            'total'          => $symbol . number_format($total, 2),
            'vat_enabled'    => $vatEnabled,
            'currency'       => $currency,
            'symbol'         => $symbol,
        ];
    }
}
