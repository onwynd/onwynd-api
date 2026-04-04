<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Services\Currency\ExchangeRateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionPlanSeeder extends Seeder
{
    protected ExchangeRateService $exchangeRateService;

    public function __construct(ExchangeRateService $exchangeRateService)
    {
        $this->exchangeRateService = $exchangeRateService;
    }

    /**
     * Convert price from base currency to target currency using exchange rates
     */
    private function convertPrice(float $basePrice, string $fromCurrency, string $toCurrency): float
    {
        try {
            if ($fromCurrency === $toCurrency) {
                return $basePrice;
            }

            $rate = $this->exchangeRateService->getRate($fromCurrency, $toCurrency);

            return $basePrice * $rate;
        } catch (\Exception $e) {
            Log::warning('Exchange rate conversion failed in seeder, using fallback rates', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'price' => $basePrice,
                'error' => $e->getMessage(),
            ]);

            // Fallback rates if exchange service fails
            $fallbackRates = [
                'NGN' => ['USD' => 0.0022, 'GBP' => 0.0018, 'EUR' => 0.0021],
                'USD' => ['NGN' => 450, 'GBP' => 0.82, 'EUR' => 0.95],
                'GBP' => ['NGN' => 550, 'USD' => 1.22, 'EUR' => 1.16],
                'EUR' => ['NGN' => 475, 'USD' => 1.05, 'GBP' => 0.86],
            ];

            return $basePrice * ($fallbackRates[$fromCurrency][$toCurrency] ?? 1.0);
        }
    }

    public function run(): void
    {
        // Base prices in NGN
        $basePrices = [
            'basic' => 2999.00,
            'premium' => 4999.00,
            'premium_yearly' => 45000.00,
            'student' => 2499.00,
            'recovery' => 24999.00,
            'institutional' => 99999.00,
            'corporate' => 299999.00,
        ];

        // Generate dynamic pricing for all currencies
        $currencies = ['NGN', 'USD', 'GBP', 'EUR'];

        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Essential mental health tools for everyone.',
                'price' => $basePrices['basic'],
                'price_ngn' => $basePrices['basic'],
                'price_usd' => $this->convertPrice($basePrices['basic'], 'NGN', 'USD'),
                'setup_fee_ngn' => 0.00,
                'setup_fee_usd' => 0.00,
                'currency' => 'NGN',
                'billing_interval' => 'monthly',
                'plan_type' => 'd2c',
                'is_active' => true,
                'features' => json_encode([
                    'daily_activity_limit' => 2,
                    'ai_message_limit' => 10,
                    'monthly_sessions' => 0,
                    'max_sessions' => 0,
                    'feature_list' => [
                        'Daily AI Check-in (10 messages)',
                        'Community Access (Read-only)',
                        '2 Wellness Activities/Day',
                        'Basic Habit Tracking',
                        'Anonymous Profile',
                    ],
                ]),
                'max_sessions' => 0,
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Complete mental health support for young professionals.',
                'price' => $basePrices['premium'],
                'price_ngn' => $basePrices['premium'],
                'price_usd' => $this->convertPrice($basePrices['premium'], 'NGN', 'USD'),
                'setup_fee_ngn' => 0.00,
                'setup_fee_usd' => 0.00,
                'currency' => 'NGN',
                'billing_interval' => 'monthly',
                'plan_type' => 'd2c',
                'is_active' => true,
                'features' => json_encode([
                    'daily_activity_limit' => null, // unlimited
                    'ai_message_limit' => null, // unlimited
                    'monthly_sessions' => 0,
                    'max_sessions' => 4,
                    'feature_list' => [
                        'Unlimited AI Check-ins',
                        '4 Therapist Sessions/Month',
                        'Unlimited Wellness Activities',
                        'Full Community Access',
                        'Advanced Habit Tracking',
                        'Gamification & Rewards',
                        'Priority Matching',
                        'VR Relaxation Modules',
                    ],
                ]),
                'max_sessions' => 4,
            ],
            [
                'name' => 'Premium (Yearly)',
                'slug' => 'premium-yearly',
                'description' => 'Complete mental health support - Save 25% with annual billing.',
                'price' => $basePrices['premium_yearly'],
                'price_ngn' => $basePrices['premium_yearly'],
                'price_usd' => $this->convertPrice($basePrices['premium_yearly'], 'NGN', 'USD'),
                'setup_fee_ngn' => 0.00,
                'setup_fee_usd' => 0.00,
                'currency' => 'NGN',
                'billing_interval' => 'yearly',
                'plan_type' => 'd2c',
                'is_active' => true,
                'features' => json_encode([
                    'daily_activity_limit' => null, // unlimited
                    'ai_message_limit' => null, // unlimited
                    'monthly_sessions' => 0,
                    'max_sessions' => 4,
                    'feature_list' => [
                        'Unlimited AI Check-ins',
                        '4 Therapist Sessions/Month',
                        'Unlimited Wellness Activities',
                        'Full Community Access',
                        'Advanced Habit Tracking',
                        'Gamification & Rewards',
                        'Priority Matching',
                        'VR Relaxation Modules',
                        '2 Months Free (Annual Discount)',
                    ],
                ]),
                'max_sessions' => 4,
            ],
            [
                'name' => 'Student',
                'slug' => 'student',
                'description' => 'Affordable support for university students.',
                'price' => $basePrices['student'],
                'price_ngn' => $basePrices['student'],
                'price_usd' => $this->convertPrice($basePrices['student'], 'NGN', 'USD'),
                'setup_fee_ngn' => 0.00,
                'setup_fee_usd' => 0.00,
                'currency' => 'NGN',
                'billing_interval' => 'monthly',
                'plan_type' => 'b2b_university',
                'features' => json_encode([
                    'daily_activity_limit' => null, // unlimited
                    'ai_message_limit' => null, // unlimited
                    'monthly_sessions' => 0,
                    'feature_list' => [
                        'Unlimited AI Check-ins',
                        '2 Therapist Sessions/Month',
                        'Full Community Access',
                        'Student Support Groups',
                        'Exam Stress Modules',
                        'Gamification',
                    ],
                ]),
                'max_sessions' => 2,
            ],
            [
                'name' => 'Recovery Program',
                'slug' => 'recovery',
                'description' => 'Intensive support for specific issues.',
                'price' => $basePrices['recovery'], // One-time fee, maintenance is 2499
                'price_ngn' => $basePrices['recovery'],
                'price_usd' => $this->convertPrice($basePrices['recovery'], 'NGN', 'USD'),
                'setup_fee_ngn' => 0.00,
                'setup_fee_usd' => 0.00,
                'currency' => 'NGN',
                'billing_interval' => 'monthly', // Technically hybrid, but base is monthly
                'plan_type' => 'd2c',
                'features' => json_encode([
                    'daily_activity_limit' => null, // unlimited
                    'ai_message_limit' => null, // unlimited
                    'monthly_sessions' => 0,
                    'feature_list' => [
                        '3 Therapist Sessions/Week (First 4 weeks)',
                        '2 Therapist Sessions/Week (Weeks 5-12)',
                        'Daily Support Group',
                        'Symptom Severity Tracking',
                        'Family Support Modules',
                        'Money-back Guarantee',
                    ],
                ]),
                'max_sessions' => 28, // Total over 12 weeks
            ],
            [
                'name' => 'Institutional',
                'slug' => 'institutional',
                'description' => 'University-sponsored mental wellness.',
                'price' => $basePrices['institutional'], // Per semester
                'price_ngn' => $basePrices['institutional'],
                'price_usd' => $this->convertPrice($basePrices['institutional'], 'NGN', 'USD'),
                'setup_fee_ngn' => 0.00,
                'setup_fee_usd' => 0.00,
                'currency' => 'NGN',
                'billing_interval' => 'quarterly', // Semester-based
                'plan_type' => 'b2b_university',
                'features' => json_encode([
                    'Unlimited AI Check-ins',
                    '4 Therapist Sessions/Month',
                    'Full Community Access',
                    'Advanced Habit Tracking',
                    'VR Relaxation Modules',
                ]),
                'max_sessions' => 4,
            ],
            // ─── Corporate (B2B) plans ─────────────────────────────────────────────
            [
                'name' => 'Starter',
                'slug' => 'corporate-starter',
                'description' => 'Mental wellness for small teams (up to 25 employees).',
                'price' => 150000.00,
                'price_ngn' => 150000.00,
                'price_usd' => $this->convertPrice(150000.00, 'NGN', 'USD'),
                'setup_fee_ngn' => 0.00,
                'setup_fee_usd' => 0.00,
                'currency' => 'NGN',
                'billing_interval' => 'monthly',
                'plan_type' => 'b2b_corporate',
                'is_active' => true,
                'features' => json_encode([
                    'seats' => 25,
                    'ai_message_limit' => null,
                    'monthly_sessions' => 25,
                    'feature_list' => [
                        'Up to 25 Employees',
                        'AI Companion for All',
                        '1 Session/Employee/Month',
                        'Manager Dashboard',
                        'Wellness Reports',
                        'Dedicated Account Manager',
                    ],
                ]),
                'max_sessions' => 25,
            ],
            [
                'name' => 'Growth',
                'slug' => 'corporate-growth',
                'description' => 'Mental wellness for growing organisations (up to 100 employees).',
                'price' => 450000.00,
                'price_ngn' => 450000.00,
                'price_usd' => $this->convertPrice(450000.00, 'NGN', 'USD'),
                'setup_fee_ngn' => 0.00,
                'setup_fee_usd' => 0.00,
                'currency' => 'NGN',
                'billing_interval' => 'monthly',
                'plan_type' => 'b2b_corporate',
                'is_active' => true,
                'features' => json_encode([
                    'seats' => 100,
                    'ai_message_limit' => null,
                    'monthly_sessions' => 100,
                    'feature_list' => [
                        'Up to 100 Employees',
                        'AI Companion for All',
                        '1 Session/Employee/Month',
                        'Advanced Analytics Dashboard',
                        'Custom Wellness Programmes',
                        'Priority Support',
                        'Quarterly ROI Reports',
                    ],
                ]),
                'max_sessions' => 100,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'corporate-enterprise',
                'description' => 'Enterprise mental wellness for large organisations (unlimited employees).',
                'price' => 999999.00,
                'price_ngn' => 999999.00,
                'price_usd' => $this->convertPrice(999999.00, 'NGN', 'USD'),
                'setup_fee_ngn' => 0.00,
                'setup_fee_usd' => 0.00,
                'currency' => 'NGN',
                'billing_interval' => 'monthly',
                'plan_type' => 'b2b_corporate',
                'is_active' => true,
                'features' => json_encode([
                    'seats' => null,
                    'ai_message_limit' => null,
                    'monthly_sessions' => null,
                    'feature_list' => [
                        'Unlimited Employees',
                        'AI Companion for All',
                        'Unlimited Sessions',
                        'Executive Wellness Coaching',
                        'White-label Option',
                        'Custom Integrations (Slack, HRMS)',
                        'Dedicated Clinical Team',
                        'SLA-backed Support',
                    ],
                ]),
                'max_sessions' => null,
            ],
        ];

        foreach ($plans as $plan) {
            $existing = SubscriptionPlan::where('slug', $plan['slug'])->first();
            if (! $existing) {
                $data = $plan;
                $data['uuid'] = (string) Str::uuid();
                SubscriptionPlan::create($data);
            } else {
                $update = $plan;
                if (empty($existing->uuid)) {
                    $update['uuid'] = (string) Str::uuid();
                } else {
                    unset($update['uuid']);
                }
                $existing->update($update);
            }
        }
    }
}
