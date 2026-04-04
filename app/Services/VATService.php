<?php

namespace App\Services;

use App\Services\PlatformSettingsService;

class VATService
{
    public function calculate(float $amount, string $currency = 'NGN'): array
    {
        $vatEnabled = filter_var(PlatformSettingsService::get('vat_enabled', 'false'), FILTER_VALIDATE_BOOLEAN);

        if (!$vatEnabled) {
            return [
                'vat_enabled'    => false,
                'vat_amount'     => 0.0,
                'vat_rate'       => 0.0,
                'vat_label'      => null,
                'total_with_vat' => $amount,
            ];
        }

        $vatRate   = (float) PlatformSettingsService::get('vat_rate', '0.075');
        $vatAmount = round($amount * $vatRate, 2);
        $vatLabel  = PlatformSettingsService::get('vat_label', 'VAT (7.5%)');

        return [
            'vat_enabled'    => true,
            'vat_amount'     => $vatAmount,
            'vat_rate'       => $vatRate,
            'vat_label'      => $vatLabel,
            'total_with_vat' => $amount + $vatAmount,
        ];
    }

    public function isEnabled(): bool
    {
        return filter_var(PlatformSettingsService::get('vat_enabled', 'false'), FILTER_VALIDATE_BOOLEAN);
    }
}
