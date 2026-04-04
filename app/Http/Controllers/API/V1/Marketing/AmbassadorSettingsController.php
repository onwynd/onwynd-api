<?php

namespace App\Http\Controllers\API\V1\Marketing;

use App\Http\Controllers\Controller;
use App\Models\AmbassadorSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AmbassadorSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $record = AmbassadorSetting::query()->latest('id')->first();
        $data = $record ? $record->data : [];

        $defaults = [
            'currency' => 'NGN',
            'individual' => [],
            'b2b' => [],
            'caps' => [
                [
                    'title' => 'Monthly Earning Cap',
                    'value' => '₦200,000/month',
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
                    'value' => '₦100,000 threshold',
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

        // Merge with defaults to ensure keys exist
        $data = array_merge($defaults, is_array($data) ? $data : []);

        return response()->json([
            'success' => true,
            'data' => $data,
            'status_code' => 200,
        ]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency' => 'required|in:NGN,USD',
            'individual' => 'array',
            'individual.*.tier' => 'required_with:individual|string',
            'individual.*.amount' => 'required_with:individual|numeric|min:0',
            'individual.*.description' => 'required_with:individual|string',
            'b2b' => 'array',
            'b2b.*.title' => 'required_with:b2b|string',
            'b2b.*.seats' => 'required_with:b2b|string',
            'b2b.*.amount' => 'required_with:b2b|numeric|min:0',
            'b2b.*.recurring' => 'required_with:b2b|string',
            'caps' => 'array',
            'caps.*.title' => 'required_with:caps|string',
            'caps.*.value' => 'required_with:caps|string',
            'caps.*.desc' => 'required_with:caps|string',
            'caps.*.note' => 'required_with:caps|string',
        ]);

        $record = AmbassadorSetting::query()->latest('id')->first();
        if (! $record) {
            $record = new AmbassadorSetting;
        }
        $record->data = $validated;
        $record->save();

        return response()->json([
            'success' => true,
            'data' => $record->data,
            'status_code' => 200,
        ]);
    }
}
