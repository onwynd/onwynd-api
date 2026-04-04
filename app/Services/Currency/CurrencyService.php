<?php

namespace App\Services\Currency;

use App\Services\ExchangeRateService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * CurrencyService
 * Handles currency formatting, conversions, and calculations
 * Primary focus on Nigerian Naira (NGN) with VAT support
 */
class CurrencyService
{
    /**
     * Currency configuration
     */
    private $nairaSymbol = '₦';

    private $nairaCode = 'NGN';

    private $nairaDecimals = 2;

    private $koboMultiplier = 100; // 1 NGN = 100 kobo

    private $nairaVatRate = 0.075; // 7.5% VAT

    private $currencies = [
        'NGN' => ['symbol' => '₦', 'decimals' => 2, 'name' => 'Nigerian Naira'],
        'USD' => ['symbol' => '$', 'decimals' => 2, 'name' => 'US Dollar'],
        'GBP' => ['symbol' => '£', 'decimals' => 2, 'name' => 'British Pound'],
        'EUR' => ['symbol' => '€', 'decimals' => 2, 'name' => 'Euro'],
        'JPY' => ['symbol' => '¥', 'decimals' => 0, 'name' => 'Japanese Yen'],
        'CAD' => ['symbol' => 'C$', 'decimals' => 2, 'name' => 'Canadian Dollar'],
        'AUD' => ['symbol' => 'A$', 'decimals' => 2, 'name' => 'Australian Dollar'],
    ];

    /**
     * Exchange rate service for currency conversions
     *
     * @var ExchangeRateService
     */
    private $exchangeRateService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->exchangeRateService = app(\App\Services\ExchangeRateService::class);
    }

    /**
     * Format amount with currency symbol
     *
     * @param  float  $amount  Amount to format
     * @param  string  $currency  Currency code (default: NGN)
     * @return string Formatted currency string
     */
    public function format(float $amount, string $currency = 'NGN'): string
    {
        try {
            $currency = strtoupper($currency);

            if (! isset($this->currencies[$currency])) {
                throw new Exception("Unsupported currency: {$currency}");
            }

            $config = $this->currencies[$currency];
            $decimals = $config['decimals'];
            $symbol = $config['symbol'];

            $formatted = number_format($amount, $decimals);

            return "{$symbol}{$formatted}";

        } catch (Exception $e) {
            Log::error('Currency formatting failed', [
                'amount' => $amount,
                'currency' => $currency,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Format Naira amount with symbol
     *
     * @param  float  $amount  Amount in Naira
     * @return string Formatted Naira string
     */
    public function formatNaira(float $amount): string
    {
        return $this->format($amount, 'NGN');
    }

    /**
     * Convert amount to kobo (smallest Naira unit)
     * Used for payment gateway integration (Paystack, Flutterwave)
     *
     * @param  float  $amountInNaira  Amount in Naira
     * @return int Amount in kobo
     */
    public function toKobo(float $amountInNaira): int
    {
        try {
            if ($amountInNaira < 0) {
                throw new Exception('Amount cannot be negative');
            }

            return (int) ($amountInNaira * $this->koboMultiplier);

        } catch (Exception $e) {
            Log::error('Kobo conversion failed', [
                'amount' => $amountInNaira,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Convert amount from kobo to Naira
     *
     * @param  int  $amountInKobo  Amount in kobo
     * @return float Amount in Naira
     */
    public function fromKobo(int $amountInKobo): float
    {
        try {
            if ($amountInKobo < 0) {
                throw new Exception('Amount cannot be negative');
            }

            return $amountInKobo / $this->koboMultiplier;

        } catch (Exception $e) {
            Log::error('Kobo conversion (reverse) failed', [
                'amount' => $amountInKobo,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate VAT for Naira amount
     * Nigerian VAT is 7.5%
     *
     * @param  float  $amount  Amount in Naira
     * @param  float|null  $vatRate  Custom VAT rate (overrides default 7.5%)
     * @return array ['amount' => original, 'vat' => vat amount, 'total' => amount + vat]
     */
    public function calculateVAT(float $amount, ?float $vatRate = null): array
    {
        try {
            $rate = $vatRate ?? $this->nairaVatRate;

            if ($rate < 0 || $rate > 1) {
                throw new Exception('VAT rate must be between 0 and 1');
            }

            $vatAmount = $amount * $rate;
            $total = $amount + $vatAmount;

            Log::info('VAT calculated', [
                'amount' => $amount,
                'vat_rate' => $rate,
                'vat_amount' => $vatAmount,
                'total' => $total,
            ]);

            return [
                'amount' => round($amount, 2),
                'vat' => round($vatAmount, 2),
                'vat_rate' => $rate * 100, // As percentage
                'total' => round($total, 2),
            ];

        } catch (Exception $e) {
            Log::error('VAT calculation failed', [
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Remove VAT from total amount
     * Reverse calculation: if total includes VAT, get the original amount
     *
     * @param  float  $totalWithVAT  Total amount including VAT
     * @param  float|null  $vatRate  Custom VAT rate (overrides default 7.5%)
     * @return array ['amount' => original, 'vat' => vat deducted, 'total' => original amount]
     */
    public function removeVAT(float $totalWithVAT, ?float $vatRate = null): array
    {
        try {
            $rate = $vatRate ?? $this->nairaVatRate;

            if ($rate < 0 || $rate > 1) {
                throw new Exception('VAT rate must be between 0 and 1');
            }

            // Formula: Original = Total / (1 + VAT Rate)
            $originalAmount = $totalWithVAT / (1 + $rate);
            $vatAmount = $totalWithVAT - $originalAmount;

            Log::info('VAT removed', [
                'total_with_vat' => $totalWithVAT,
                'vat_rate' => $rate,
                'vat_amount' => $vatAmount,
                'original_amount' => $originalAmount,
            ]);

            return [
                'amount' => round($originalAmount, 2),
                'vat' => round($vatAmount, 2),
                'vat_rate' => $rate * 100,
                'total' => round($totalWithVAT, 2),
            ];

        } catch (Exception $e) {
            Log::error('VAT removal failed', [
                'total' => $totalWithVAT,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Validate amount is within acceptable range for Naira
     *
     * @param  float  $amount  Amount in Naira
     * @return bool True if valid
     */
    public function validateNairaAmount(float $amount): bool
    {
        $minAmount = config('payment.naira.min_amount_naira', 100);
        $maxAmount = config('payment.naira.max_amount_naira', 100000000);

        return $amount >= $minAmount && $amount <= $maxAmount;
    }

    /**
     * Get minimum transaction amount for currency
     *
     * @param  string  $currency  Currency code
     * @return float Minimum amount
     */
    public function getMinimumAmount(string $currency = 'NGN'): float
    {
        $minimums = [
            'NGN' => 100,
            'USD' => 1,
            'GBP' => 0.99,
            'EUR' => 0.95,
            'JPY' => 100,
            'CAD' => 1.25,
            'AUD' => 1.50,
        ];

        return $minimums[strtoupper($currency)] ?? 1;
    }

    /**
     * Get maximum transaction amount for currency
     *
     * @param  string  $currency  Currency code
     * @return float Maximum amount
     */
    public function getMaximumAmount(string $currency = 'NGN'): float
    {
        $maximums = [
            'NGN' => 100000000,
            'USD' => 1000000,
            'GBP' => 1000000,
            'EUR' => 1000000,
            'JPY' => 100000000,
            'CAD' => 1000000,
            'AUD' => 1000000,
        ];

        return $maximums[strtoupper($currency)] ?? 100000;
    }

    /**
     * Round amount to currency's decimal places
     *
     * @param  float  $amount  Amount to round
     * @param  string  $currency  Currency code
     * @return float Rounded amount
     */
    public function round(float $amount, string $currency = 'NGN'): float
    {
        $currency = strtoupper($currency);

        if (! isset($this->currencies[$currency])) {
            return round($amount, 2);
        }

        $decimals = $this->currencies[$currency]['decimals'];

        return round($amount, $decimals);
    }

    /**
     * Get currency symbol
     *
     * @param  string  $currency  Currency code
     * @return string Currency symbol
     */
    public function getSymbol(string $currency = 'NGN'): string
    {
        $currency = strtoupper($currency);

        return $this->currencies[$currency]['symbol'] ?? $currency;
    }

    /**
     * Get currency name
     *
     * @param  string  $currency  Currency code
     * @return string Currency name
     */
    public function getName(string $currency = 'NGN'): string
    {
        $currency = strtoupper($currency);

        return $this->currencies[$currency]['name'] ?? $currency;
    }

    /**
     * Check if currency is zero-decimal (like JPY)
     *
     * @param  string  $currency  Currency code
     * @return bool True if zero-decimal
     */
    public function isZeroDecimal(string $currency): bool
    {
        $currency = strtoupper($currency);

        return isset($this->currencies[$currency]) && $this->currencies[$currency]['decimals'] === 0;
    }

    /**
     * Convert between currencies using exchange rate service
     * Uses database-backed rates with API fallback and cache support
     *
     * @param  float  $amount  Amount to convert
     * @param  string  $fromCurrency  Source currency
     * @param  string  $toCurrency  Target currency
     * @return float Converted amount
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        try {
            $fromCurrency = strtoupper($fromCurrency);
            $toCurrency = strtoupper($toCurrency);

            if ($fromCurrency === $toCurrency) {
                return $amount;
            }

            // Use exchange rate service for conversion
            $converted = $this->exchangeRateService->convert($amount, $fromCurrency, $toCurrency);

            return $this->round($converted, $toCurrency);

        } catch (Exception $e) {
            Log::error('Currency conversion failed', [
                'amount' => $amount,
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'error' => $e->getMessage(),
            ]);

            return $amount; // Return original if conversion fails
        }
    }

    /**
     * Parse currency amount from string
     * Handles formats like "₦5,000.50", "$100.00", etc.
     *
     * @param  string  $amountString  Amount string with symbol
     * @return float Parsed amount
     */
    public function parseAmount(string $amountString): float
    {
        try {
            // Remove currency symbols
            $amount = preg_replace('/[^0-9.]/', '', $amountString);

            // Remove commas
            $amount = str_replace(',', '', $amount);

            return (float) $amount;

        } catch (Exception $e) {
            Log::error('Amount parsing failed', [
                'amount_string' => $amountString,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get all supported currencies
     *
     * @return array Array of currencies with details
     */
    public function getSupportedCurrencies(): array
    {
        return $this->currencies;
    }

    /**
     * Check if currency is supported
     *
     * @param  string  $currency  Currency code
     * @return bool True if supported
     */
    public function isSupported(string $currency): bool
    {
        return isset($this->currencies[strtoupper($currency)]);
    }

    /**
     * Format payment breakdown with VAT
     * Useful for invoices and receipts
     *
     * @param  float  $amount  Amount before VAT
     * @param  string  $currency  Currency code
     * @return array Formatted payment breakdown
     */
    public function getPaymentBreakdown(float $amount, string $currency = 'NGN'): array
    {
        try {
            $vat = $currency === 'NGN' ? $this->calculateVAT($amount) : null;

            $breakdown = [
                'currency' => $currency,
                'subtotal' => $this->format($amount, $currency),
                'subtotal_amount' => $amount,
            ];

            if ($vat) {
                $breakdown['vat_rate'] = $vat['vat_rate'].'%';
                $breakdown['vat_amount'] = $this->format($vat['vat'], $currency);
                $breakdown['vat_numeric'] = $vat['vat'];
                $breakdown['total'] = $this->format($vat['total'], $currency);
                $breakdown['total_amount'] = $vat['total'];
            } else {
                $breakdown['total'] = $this->format($amount, $currency);
                $breakdown['total_amount'] = $amount;
            }

            return $breakdown;

        } catch (Exception $e) {
            Log::error('Payment breakdown formatting failed', [
                'amount' => $amount,
                'currency' => $currency,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
