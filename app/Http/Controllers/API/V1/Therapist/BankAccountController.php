<?php

namespace App\Http\Controllers\API\V1\Therapist;

use App\Http\Controllers\API\BaseController;
use App\Models\TherapistProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BankAccountController extends BaseController
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_number' => 'required|string|min:10|max:20',
            'bank_code' => 'required|string|max:10',
            'bank_name' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $user = $request->user();
        $profile = TherapistProfile::where('user_id', $user->id)->first();

        if (! $profile) {
            return $this->sendError('Therapist profile not found.', [], 404);
        }

        // Clear recipient_code when bank account changes so a new one is created on next payout
        $profile->update([
            'account_number' => $request->account_number,
            'bank_code' => $request->bank_code,
            'bank_name' => $request->bank_name,
            'account_name' => $request->account_name,
            'recipient_code' => null,
        ]);

        return $this->sendResponse([
            'account_number' => $profile->account_number,
            'bank_code' => $profile->bank_code,
            'bank_name' => $profile->bank_name,
            'account_name' => $profile->account_name,
        ], 'Bank account saved successfully.');
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $profile = TherapistProfile::where('user_id', $user->id)->first();

        if (! $profile) {
            return $this->sendError('Profile not found.', [], 404);
        }

        return $this->sendResponse([
            'account_number' => $profile->account_number,
            'bank_code' => $profile->bank_code,
            'bank_name' => $profile->bank_name,
            'account_name' => $profile->account_name,
            'has_bank_account' => ! empty($profile->account_number),
        ], 'Bank account retrieved.');
    }
}
