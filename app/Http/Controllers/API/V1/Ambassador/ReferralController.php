<?php

namespace App\Http\Controllers\API\V1\Ambassador;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReferralController extends BaseController
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $ambassador = $user->ambassador;

        if (! $ambassador) {
            return $this->sendError('Ambassador profile not found.', [], 404);
        }

        $referrals = $ambassador->referrals()
            ->with('referredUser:id,first_name,last_name,email,created_at')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return $this->sendResponse($referrals, 'Referral history retrieved.');
    }
}
