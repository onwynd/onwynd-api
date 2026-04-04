<?php

namespace App\Services\CurrencyService;

/**
 * Currency Service
 * Handles currency formatting, conversion, and calculations
 */
class CurrencyService
{
    /**
     * Supported currencies
     */
    private const SUPPORTED_CURRENCIES = [
        'NGN' => ['symbol' => '₦', 'decimals' => 2],
        'USD' => ['symbol' => '$', 'decimals' => 2],
        'GBP' => ['symbol' => '£', 'decimals' => 2],
        'EUR' => ['symbol' => '€', 'decimals' => 2],
        'JPY' => ['symbol' => '¥', 'decimals' => 0],
        'CAD' => ['symbol' => 'C$', 'decimals' => 2],
        'AUD' => ['symbol' => 'A$', 'decimals' => 2],
    ];

    /**
     * Exchange rates (base: NGN)
     */
    private const EXCHANGE_RATES = [
        'NGN' => 1,
        'USD' => 0.00067,
        'GBP' => 0.00053,
        'EUR' => 0.00062,
        'JPY' => 0.099,
        'CAD' => 0.00091,
        'AUD' => 0.00103,
    ];

    /**
     * Format amount with currency symbol
     */
    public function format($amount, $currency = 'NGN')
    {
        if (! isset(self::SUPPORTED_CURRENCIES[$currency])) {
            throw new \Exception("Currency {$currency} not supported");
        }

        $config = self::SUPPORTED_CURRENCIES[$currency];
        $symbol = $config['symbol'];
        $decimals = $config['decimals'];

        return $symbol.number_format($amount, $decimals);
    }

    /**
     * Format Naira specifically
     */
    public function formatNaira($amount)
    {
        return $this->format($amount, 'NGN');
    }

    /**
     * Convert NGN to kobo (for payment gateways)
     */
    public function toKobo($naira)
    {
        return (int) ($naira * 100);
    }

    /**
     * Convert kobo to NGN
     */
    public function fromKobo($kobo)
    {
        return $kobo / 100;
    }

    /**
     * Calculate VAT (Nigerian standard: 7.5%)
     */
    public function calculateVAT($amount, $rate = 7.5)
    {
        $vat = ($amount * $rate) / 100;

        return [
            'amount' => $amount,
            'vat' => round($vat, 2),
            'vat_rate' => $rate,
            'total' => round($amount + $vat, 2),
        ];
    }

    /**
     * Remove VAT from inclusive amount
     */
    public function removeVAT($total, $rate = 7.5)
    {
        $amount = $total / (1 + ($rate / 100));

        return [
            'amount' => round($amount, 2),
            'vat' => round($total - $amount, 2),
            'vat_rate' => $rate,
            'total' => $total,
        ];
    }

    /**
     * Convert between currencies
     */
    public function convert($amount, $from = 'NGN', $to = 'USD')
    {
        if (! isset(self::EXCHANGE_RATES[$from]) || ! isset(self::EXCHANGE_RATES[$to])) {
            throw new \Exception('Currency conversion not supported');
        }

        $amountInBase = $amount / self::EXCHANGE_RATES[$from];
        $convertedAmount = $amountInBase * self::EXCHANGE_RATES[$to];

        return round($convertedAmount, self::SUPPORTED_CURRENCIES[$to]['decimals']);
    }

    /**
     * Validate amount
     */
    public function validateAmount($amount, $currency = 'NGN')
    {
        $min = match ($currency) {
            'NGN' => 100,
            'USD' => 1,
            'GBP' => 1,
            'EUR' => 1,
            'JPY' => 100,
            'CAD' => 1,
            'AUD' => 1,
            default => 0
        };

        $max = match ($currency) {
            'NGN' => 100_000_000,
            'USD' => 100_000,
            'GBP' => 100_000,
            'EUR' => 100_000,
            'JPY' => 10_000_000,
            'CAD' => 100_000,
            'AUD' => 100_000,
            default => PHP_INT_MAX
        };

        return $amount >= $min && $amount <= $max;
    }

    /**
     * Get payment breakdown for invoices
     */
    public function getPaymentBreakdown($amount, $vatRate = 7.5, $currency = 'NGN')
    {
        $vat = ($amount * $vatRate) / 100;
        $total = $amount + $vat;

        return [
            'subtotal' => $this->format($amount, $currency),
            'vat' => $this->format($vat, $currency),
            'total' => $this->format($total, $currency),
            'vat_rate' => $vatRate.'%',
            'currency' => $currency,
        ];
    }

    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies()
    {
        return array_keys(self::SUPPORTED_CURRENCIES);
    }

    /**
     * Round amount based on currency
     */
    public function round($amount, $currency = 'NGN')
    {
        $decimals = self::SUPPORTED_CURRENCIES[$currency]['decimals'] ?? 2;

        return round($amount, $decimals);
    }
}
