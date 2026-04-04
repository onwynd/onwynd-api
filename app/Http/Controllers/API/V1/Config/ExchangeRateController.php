<?php

namespace App\Http\Controllers\API\V1\Config;

use App\Http\Controllers\API\BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ExchangeRateController
 * ──────────────────────
 * Returns the current NGN/USD exchange rate.
 *
 * Source: frankfurter.app (ECB-backed, free, no API key required).
 * Cache: 24 hours — respects free tier limits.
 *
 * GET /api/v1/config/exchange-rate
 */
class ExchangeRateController extends BaseController
{
    /** Fallback rate if the upstream API is unavailable */
    private const FALLBACK_RATE_NGN = 1580.0;

    private const CACHE_KEY = 'exchange_rate_usd_ngn';
    private const CACHE_TTL = 86400; // 24 hours in seconds
    private const FRANKFURTER_URL = 'https://api.frankfurter.app/latest';

    public function index()
    {
        $rate = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return $this->fetchFromUpstream();
        });

        return $this->sendResponse([
            'usd_to_ngn' => $rate,
            'base'        => 'USD',
            'target'      => 'NGN',
            'source'      => 'frankfurter.app',
            'cached_for'  => self::CACHE_TTL,
        ], 'Exchange rate retrieved');
    }

    private function fetchFromUpstream(): float
    {
        try {
            $response = Http::timeout(5)->get(self::FRANKFURTER_URL, [
                'from'   => 'USD',
                'to'     => 'NGN',
                'amount' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $rate = $data['rates']['NGN'] ?? null;
                if ($rate && is_numeric($rate) && $rate > 0) {
                    return (float) $rate;
                }
            }

            Log::warning('ExchangeRateController: upstream returned unexpected data', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ExchangeRateController: upstream request failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return self::FALLBACK_RATE_NGN;
    }
}
