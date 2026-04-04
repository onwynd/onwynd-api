<?php

namespace App\Http\Controllers\API\V1\Account;

use App\Http\Controllers\API\BaseController;
use App\Models\UserReferral;
use App\Services\UserReferralService;
use Illuminate\Http\Request;

class ReferralController extends BaseController
{
    public function __construct(private UserReferralService $referralService)
    {
    }

    /**
     * Get (or generate) the authenticated user's referral code + summary stats.
     * GET /api/v1/account/referral-code
     */
    public function getCode(Request $request)
    {
        $user = $request->user();
        $code = $this->referralService->getOrCreateCode($user);

        $successfulReferrals = UserReferral::where('referrer_user_id', $user->id)
            ->whereIn('status', ['referee_rewarded', 'referrer_rewarded', 'fully_rewarded'])
            ->count();

        return $this->sendResponse([
            'code'                    => $code->code,
            'tier'                    => $code->tier,
            'uses_count'              => $code->uses_count,
            'successful_referrals'    => $successfulReferrals,
            'referral_ai_bonus'       => $user->referral_ai_bonus ?? 0,
            'pending_discount_earned' => $user->pending_referral_discount ?? 0,
        ], 'Referral code retrieved.');
    }

    /**
     * List referrals made by the authenticated user.
     * GET /api/v1/account/referrals
     */
    public function history(Request $request)
    {
        $referrals = UserReferral::with('referee:id,first_name,email,created_at')
            ->where('referrer_user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->sendResponse($referrals, 'Referral history retrieved.');
    }
}
