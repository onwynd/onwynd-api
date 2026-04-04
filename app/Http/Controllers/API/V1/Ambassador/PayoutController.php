<?php

namespace App\Http\Controllers\API\V1\Ambassador;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PayoutController extends BaseController
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $ambassador = $user->ambassador;

        if (! $ambassador) {
            return $this->sendError('Ambassador profile not found.', [], 404);
        }

        $payouts = $ambassador->payouts()
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return $this->sendResponse($payouts, 'Payout history retrieved.');
    }

    public function requestPayout(Request $request)
    {
        $user = Auth::user();
        $ambassador = $user->ambassador;

        if (! $ambassador) {
            return $this->sendError('Ambassador profile not found.', [], 404);
        }

        // Calculate available balance (mock logic)
        $totalEarnings = $ambassador->referrals()->where('status', 'paid')->sum('amount');
        $totalPaidOut = $ambassador->payouts()->where('status', 'paid')->sum('amount');
        $availableBalance = $totalEarnings - $totalPaidOut;

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:10|max:'.$availableBalance,
            'payment_method' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // Create payout request (Assuming AmbassadorPayout model exists as per Ambassador model relation)
        $payout = $ambassador->payouts()->create([
            'amount' => $request->amount,
            'status' => 'pending',
            'payment_method' => $request->payment_method,
        ]);

        return $this->sendResponse($payout, 'Payout requested successfully.');
    }
}
