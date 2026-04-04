<?php

namespace Database\Seeders;

use App\Models\AmbassadorSetting;
use Illuminate\Database\Seeder;

class AmbassadorSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'currency' => 'NGN',
            'individual' => [
                ['tier' => 'Free Trial', 'amount' => 0, 'description' => 'User signs up but doesn\'t convert'],
                ['tier' => 'Premium', 'amount' => 50000, 'description' => 'User subscribes to Premium plan'],
                ['tier' => 'Recovery', 'amount' => 75000, 'description' => 'User subscribes to Recovery plan'],
            ],
            'b2b' => [
                [
                    'title' => 'Small Business',
                    'seats' => '10–49 seats',
                    'amount' => 500000,
                    'recurring' => '5% monthly recurring',
                ],
                [
                    'title' => 'Mid-Market',
                    'seats' => '50–249 seats',
                    'amount' => 2000000,
                    'recurring' => '7.5% monthly recurring',
                ],
                [
                    'title' => 'Enterprise',
                    'seats' => '250+ seats',
                    'amount' => 5000000,
                    'recurring' => '10% monthly recurring',
                ],
            ],
            'caps' => [
                [
                    'title' => 'Monthly Earning Cap',
                    'value' => '₦100,000/month',
                    'desc' => 'Maximum earnings per ambassador per month',
                    'note' => 'Contact us if consistently hitting this limit',
                ],
                [
                    'title' => 'Daily Referral Velocity',
                    'value' => '50 referrals/day',
                    'desc' => 'Maximum new user signups per day',
                    'note' => 'Anti-fraud measure; prevents bot farming',
                ],
                [
                    'title' => 'Payment Schedule',
                    'value' => '7 days after trigger',
                    'desc' => 'Commission held during refund window',
                    'note' => 'Released automatically if no refund requested',
                ],
                [
                    'title' => 'Minimum Payout',
                    'value' => '₦1,000 threshold',
                    'desc' => 'Minimum balance before payment',
                    'note' => 'Rolls over to next month if below',
                ],
                [
                    'title' => 'Payment Method',
                    'value' => 'Direct deposit',
                    'desc' => 'Paid to your bank account',
                    'note' => 'PayPal available for international ambassadors',
                ],
                [
                    'title' => 'Tax Reporting',
                    'value' => 'Nigerian tax compliance',
                    'desc' => 'You are responsible for taxes',
                    'note' => 'Forms issued per regulatory requirements',
                ],
            ],
        ];

        AmbassadorSetting::query()->delete();
        AmbassadorSetting::create(['data' => $data]);
    }
}
