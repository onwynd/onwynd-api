<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\PayoutSetting;
use Illuminate\Http\Request;

class PayoutSettingController extends BaseController
{
    public function index()
    {
        return $this->sendResponse(PayoutSetting::all(), 'Payout settings retrieved.');
    }

    public function update(Request $request, string $role)
    {
        $setting = PayoutSetting::firstOrCreate(
            ['role' => $role],
            ['payout_day' => 15, 'minimum_amount_kobo' => 500000, 'currency' => 'NGN', 'provider' => 'paystack']
        );

        $validated = $request->validate([
            'payout_day'          => 'sometimes|integer|min:1|max:28',
            'minimum_amount_kobo' => 'sometimes|integer|min:0',
            'currency'            => 'sometimes|string|max:10',
            'provider'            => 'sometimes|in:paystack,lenco,manual',
            'cycle_description'   => 'sometimes|string|max:255',
            'auto_process'        => 'sometimes|boolean',
        ]);

        $setting->update($validated);

        return $this->sendResponse($setting->fresh(), 'Payout settings updated.');
    }
}
