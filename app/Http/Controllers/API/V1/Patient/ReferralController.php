<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\Ambassador;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ReferralController extends BaseController
{
    /**
     * Get user's referral code
     */
    public function getReferralCode()
    {
        $user = Auth::user();

        // Check if user is an ambassador
        $ambassador = $user->ambassador;
        if (! $ambassador) {
            return $this->sendError('Ambassador profile not found. Please apply to become an ambassador first.', [], 404);
        }

        $referralCode = $ambassador->referralCode;

        if (! $referralCode) {
            return $this->sendError('Referral code not found.', [], 404);
        }

        return $this->sendResponse([
            'code' => $referralCode->code,
            'link' => config('app.frontend_url').'/join/'.$referralCode->code,
            'created_at' => $referralCode->created_at,
            'expires_at' => $referralCode->expires_at,
            'uses_count' => $referralCode->uses_count,
            'max_uses' => $referralCode->max_uses,
        ], 'Referral code retrieved successfully.');
    }

    /**
     * Generate new referral code
     */
    public function generateReferralCode()
    {
        $user = Auth::user();

        // Check if user is an ambassador
        $ambassador = $user->ambassador;
        if (! $ambassador) {
            return $this->sendError('Ambassador profile not found. Please apply to become an ambassador first.', [], 404);
        }

        // Generate new referral code
        $code = Str::upper(Str::random(8));

        $referralCode = $ambassador->referralCode()->updateOrCreate(
            ['ambassador_id' => $ambassador->id],
            [
                'code' => $code,
                'expires_at' => now()->addMonths(6),
                'uses_count' => 0,
                'max_uses' => 100,
            ]
        );

        return $this->sendResponse([
            'code' => $referralCode->code,
            'link' => config('app.frontend_url').'/join/'.$referralCode->code,
            'created_at' => $referralCode->created_at,
            'expires_at' => $referralCode->expires_at,
            'uses_count' => $referralCode->uses_count,
            'max_uses' => $referralCode->max_uses,
        ], 'Referral code generated successfully.');
    }

    /**
     * Get all referrals for the user
     */
    public function getReferrals(Request $request)
    {
        $user = Auth::user();

        // Check if user is an ambassador
        $ambassador = $user->ambassador;
        if (! $ambassador) {
            return $this->sendError('Ambassador profile not found.', [], 404);
        }

        $referrals = $ambassador->referrals()
            ->with('referredUser:id,first_name,last_name,email,created_at')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return $this->sendResponse($referrals, 'Referrals retrieved successfully.');
    }

    /**
     * Get referral statistics
     */
    public function getStats()
    {
        $user = Auth::user();

        // Check if user is an ambassador
        $ambassador = $user->ambassador;
        if (! $ambassador) {
            return $this->sendError('Ambassador profile not found.', [], 404);
        }

        $totalEarnings = $ambassador->referrals()->sum('amount') ?? 0;

        $stats = [
            'total_referrals'     => $ambassador->referrals()->count(),
            'completed_referrals' => $ambassador->referrals()->where('status', 'completed')->count(),
            'pending_referrals'   => $ambassador->referrals()->where('status', 'pending')->count(),
            'expired_referrals'   => $ambassador->referrals()->where('status', 'cancelled')->count(),
            'total_earnings'      => $totalEarnings,
            'available_credits'   => $ambassador->referrals()->where('status', 'completed')->sum('amount') ?? 0,
            'lifetime_earnings'   => $totalEarnings,
            'current_streak'      => null,
            'best_month'          => null,
        ];

        return $this->sendResponse($stats, 'Referral statistics retrieved successfully.');
    }

    /**
     * Get referral rewards
     */
    public function getRewards(Request $request)
    {
        $user = Auth::user();

        // Check if user is an ambassador
        $ambassador = $user->ambassador;
        if (! $ambassador) {
            return $this->sendError('Ambassador profile not found.', [], 404);
        }

        $rewards = ReferralReward::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return $this->sendResponse($rewards, 'Referral rewards retrieved successfully.');
    }

    /**
     * Redeem referral reward
     */
    public function redeemReward($rewardId)
    {
        $user = Auth::user();

        $reward = ReferralReward::where('user_id', $user->id)
            ->where('id', $rewardId)
            ->where('status', 'pending')
            ->first();

        if (! $reward) {
            return $this->sendError('Reward not found or already redeemed.', [], 404);
        }

        // Reward payout processing is not yet implemented — do not mark as paid without actual disbursement
        return $this->sendError('Reward redemption is not yet available. Your reward is saved and will be processed soon.', [], 501);
    }

    /**
     * Get referral leaderboard
     */
    public function getLeaderboard(Request $request)
    {
        $period = $request->get('period', 'monthly');

        // Get top ambassadors by referrals
        $leaderboard = Ambassador::with('user:id,first_name,last_name,avatar')
            ->withCount('referrals')
            ->orderBy('referrals_count', 'desc')
            ->limit(10)
            ->get();

        return $this->sendResponse($leaderboard, 'Referral leaderboard retrieved successfully.');
    }

    /**
     * Share referral link via email
     */
    public function shareViaEmail(Request $request)
    {
        $request->validate([
            'emails' => 'required|array|min:1',
            'emails.*' => 'email',
            'message' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();

        // Check if user is an ambassador
        $ambassador = $user->ambassador;
        if (! $ambassador) {
            return $this->sendError('Ambassador profile not found.', [], 404);
        }

        $referralCode = $ambassador->referralCode;
        if (! $referralCode) {
            return $this->sendError('Referral code not found.', [], 404);
        }

        // Email invite sending is not yet implemented
        return $this->sendError('Email invitations are not yet available. Share your referral link manually.', [], 501);
    }

    /**
     * Track referral link click
     */
    public function trackClick(Request $request)
    {
        $request->validate([
            'referral_code' => 'required|string',
        ]);

        // Click tracking is not yet implemented
        return $this->sendError('Click tracking is not yet available.', [], 501);
    }
}
