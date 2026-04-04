<?php

namespace App\Services\Currency;

use App\Models\ExchangeRate;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    private string $baseCurrency;

    private bool $cacheEnabled;

    private int $cacheTtl;

    private string $cacheKeyPrefix;

    private array $fallbackRates;

    public function __construct()
    {
        $this->baseCurrency = config('exchange_rates.base_currency', 'NGN');
        $this->cacheEnabled = config('exchange_rates.cache.enabled', true);
        $this->cacheTtl = config('exchange_rates.cache.ttl', 3600);
        $this->cacheKeyPrefix = config('exchange_rates.cache.key_prefix', 'exchange_rates');
        $this->fallbackRates = config('exchange_rates.fallback_rates', []);
    }

    /**
     * Get exchange rate for a currency pair.
     *
     * @throws Exception
     */
    public function getRate(string $fromCurrency, string $toCurrency): float
    {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        // Try to get from cache first
        if ($this->cacheEnabled) {
            $cachedRate = $this->getCachedRate($fromCurrency, $toCurrency);
            if ($cachedRate !== null) {
                return $cachedRate;
            }
        }

        // Try to get from database
        $rate = $this->getDatabaseRate($fromCurrency, $toCurrency);

        if ($rate !== null) {
            if ($this->cacheEnabled) {
                $this->cacheRate($fromCurrency, $toCurrency, $rate);
            }

            return $rate;
        }

        // Try to get from API
        $rate = $this->getApiRate($fromCurrency, $toCurrency);

        if ($rate !== null) {
            // Store in database for future use
            $this->storeRate($fromCurrency, $toCurrency, $rate, 'api');

            if ($this->cacheEnabled) {
                $this->cacheRate($fromCurrency, $toCurrency, $rate);
            }

            return $rate;
        }

        // Use fallback rates as last resort
        $rate = $this->getFallbackRate($fromCurrency, $toCurrency);

        if ($rate !== null) {
            Log::warning('Using fallback exchange rate', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'rate' => $rate,
            ]);

            return $rate;
        }

        throw new Exception("Exchange rate not available for {$fromCurrency} to {$toCurrency}");
    }

    /**
     * Convert amount between currencies.
     *
     * @throws Exception
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $rate = $this->getRate($fromCurrency, $toCurrency);
        $converted = $amount * $rate;

        Log::info('Currency conversion performed', [
            'amount' => $amount,
            'from' => $fromCurrency,
            'to' => $toCurrency,
            'rate' => $rate,
            'converted' => $converted,
        ]);

        return $converted;
    }

    /**
     * Get cached exchange rate.
     */
    private function getCachedRate(string $fromCurrency, string $toCurrency): ?float
    {
        $cacheKey = $this->getCacheKey($fromCurrency, $toCurrency);

        try {
            $rate = Cache::get($cacheKey);
            if ($rate !== null) {
                Log::debug('Exchange rate retrieved from cache', [
                    'from' => $fromCurrency,
                    'to' => $toCurrency,
                    'rate' => $rate,
                ]);

                return (float) $rate;
            }
        } catch (Exception $e) {
            Log::error('Failed to retrieve exchange rate from cache', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Cache exchange rate.
     */
    private function cacheRate(string $fromCurrency, string $toCurrency, float $rate): void
    {
        $cacheKey = $this->getCacheKey($fromCurrency, $toCurrency);

        try {
            Cache::put($cacheKey, $rate, $this->cacheTtl);
            Log::debug('Exchange rate cached', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'rate' => $rate,
                'ttl' => $this->cacheTtl,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to cache exchange rate', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'rate' => $rate,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get cache key for currency pair.
     */
    private function getCacheKey(string $fromCurrency, string $toCurrency): string
    {
        return "{$this->cacheKeyPrefix}:{$fromCurrency}:{$toCurrency}";
    }

    /**
     * Get exchange rate from database.
     */
    private function getDatabaseRate(string $fromCurrency, string $toCurrency): ?float
    {
        try {
            // Direct rate first
            $rate = ExchangeRate::getRate($fromCurrency, $toCurrency);
            if ($rate) {
                return (float) $rate->rate;
            }

            // Try inverse rate
            $inverseRate = ExchangeRate::getRate($toCurrency, $fromCurrency);
            if ($inverseRate) {
                return (float) (1 / $inverseRate->rate);
            }

            // Try converting through base currency
            if ($fromCurrency !== $this->baseCurrency && $toCurrency !== $this->baseCurrency) {
                $fromToBase = ExchangeRate::getRate($fromCurrency, $this->baseCurrency);
                $baseToTo = ExchangeRate::getRate($this->baseCurrency, $toCurrency);

                if ($fromToBase && $baseToTo) {
                    return (float) ($fromToBase->rate * $baseToTo->rate);
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to retrieve exchange rate from database', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Get exchange rate from API.
     */
    private function getApiRate(string $fromCurrency, string $toCurrency): ?float
    {
        $provider = config('exchange_rates.api.provider');
        $apiKey = config('exchange_rates.api.key');
        $baseUrl = config('exchange_rates.api.base_url');
        $timeout = config('exchange_rates.api.timeout', 30);
        $retryAttempts = config('exchange_rates.api.retry_attempts', 3);

        if (! $provider || ! $apiKey || ! $baseUrl) {
            Log::debug('Exchange rate API not configured', [
                'provider' => $provider,
                'has_key' => ! empty($apiKey),
                'has_url' => ! empty($baseUrl),
            ]);

            return null;
        }

        try {
            $response = Http::timeout($timeout)
                ->retry($retryAttempts, 100)
                ->get($this->buildApiUrl($provider, $baseUrl, $fromCurrency, $toCurrency, $apiKey));

            if ($response->successful()) {
                $rate = $this->parseApiResponse($provider, $response->json(), $fromCurrency, $toCurrency);
                if ($rate !== null) {
                    Log::info('Exchange rate retrieved from API', [
                        'provider' => $provider,
                        'from' => $fromCurrency,
                        'to' => $toCurrency,
                        'rate' => $rate,
                    ]);

                    return $rate;
                }
            } else {
                Log::error('Exchange rate API request failed', [
                    'provider' => $provider,
                    'from' => $fromCurrency,
                    'to' => $toCurrency,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (Exception $e) {
            Log::error('Exchange rate API request error', [
                'provider' => $provider,
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Build API URL based on provider.
     */
    private function buildApiUrl(string $provider, string $baseUrl, string $fromCurrency, string $toCurrency, string $apiKey): string
    {
        return match ($provider) {
            'exchangerate-api' => "{$baseUrl}/v4/{$apiKey}/pair/{$fromCurrency}/{$toCurrency}",
            'fixer' => "{$baseUrl}/convert?access_key={$apiKey}&from={$fromCurrency}&to={$toCurrency}&amount=1",
            'openexchangerates' => "{$baseUrl}/api/latest.json?app_id={$apiKey}&base={$fromCurrency}&symbols={$toCurrency}",
            default => "{$baseUrl}/latest?access_key={$apiKey}&base={$fromCurrency}&symbols={$toCurrency}",
        };
    }

    /**
     * Parse API response based on provider.
     */
    private function parseApiResponse(string $provider, array $response, string $fromCurrency, string $toCurrency): ?float
    {
        return match ($provider) {
            'exchangerate-api' => isset($response['conversion_rate']) ? (float) $response['conversion_rate'] : null,
            'fixer' => isset($response['result']) ? (float) $response['result'] : null,
            'openexchangerates' => isset($response['rates'][$toCurrency]) ? (float) $response['rates'][$toCurrency] : null,
            default => null,
        };
    }

    /**
     * Store exchange rate in database.
     */
    private function storeRate(string $fromCurrency, string $toCurrency, float $rate, string $source): void
    {
        try {
            ExchangeRate::updateRate($fromCurrency, $toCurrency, $rate, $source);

            // Also store the inverse rate
            if ($rate > 0) {
                ExchangeRate::updateRate($toCurrency, $fromCurrency, 1 / $rate, $source);
            }
        } catch (Exception $e) {
            Log::error('Failed to store exchange rate in database', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'rate' => $rate,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get fallback exchange rate.
     */
    private function getFallbackRate(string $fromCurrency, string $toCurrency): ?float
    {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        // Direct rate
        if (isset($this->fallbackRates[$fromCurrency]) && isset($this->fallbackRates[$toCurrency])) {
            return $this->fallbackRates[$toCurrency] / $this->fallbackRates[$fromCurrency];
        }

        // Inverse rate
        if (isset($this->fallbackRates[$toCurrency]) && isset($this->fallbackRates[$fromCurrency])) {
            return $this->fallbackRates[$fromCurrency] / $this->fallbackRates[$toCurrency];
        }

        return null;
    }

    /**
     * Update all exchange rates from API.
     */
    public function updateRatesFromApi(): array
    {
        $results = [
            'updated' => [],
            'failed' => [],
            'skipped' => [],
        ];

        $currencies = ['USD', 'GBP', 'EUR', 'JPY', 'CAD', 'AUD'];
        $baseCurrency = $this->baseCurrency;

        foreach ($currencies as $currency) {
            if ($currency === $baseCurrency) {
                continue;
            }

            try {
                $rate = $this->getApiRate($baseCurrency, $currency);
                if ($rate !== null) {
                    $this->storeRate($baseCurrency, $currency, $rate, 'api');
                    $results['updated'][] = [
                        'currency' => $currency,
                        'rate' => $rate,
                    ];
                } else {
                    $results['failed'][] = [
                        'currency' => $currency,
                        'reason' => 'API returned no rate',
                    ];
                }
            } catch (Exception $e) {
                $results['failed'][] = [
                    'currency' => $currency,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        Log::info('Exchange rates update completed', $results);

        return $results;
    }

    /**
     * Clear cached exchange rates.
     */
    public function clearCache(): void
    {
        try {
            $currencies = ['USD', 'GBP', 'EUR', 'JPY', 'CAD', 'AUD'];
            $baseCurrency = $this->baseCurrency;

            foreach ($currencies as $currency) {
                if ($currency === $baseCurrency) {
                    continue;
                }

                $cacheKey = $this->getCacheKey($baseCurrency, $currency);
                Cache::forget($cacheKey);

                $inverseCacheKey = $this->getCacheKey($currency, $baseCurrency);
                Cache::forget($inverseCacheKey);
            }

            Log::info('Exchange rate cache cleared');
        } catch (Exception $e) {
            Log::error('Failed to clear exchange rate cache', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
