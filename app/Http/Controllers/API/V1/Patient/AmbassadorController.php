<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\Ambassador;
use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AmbassadorController extends BaseController
{
    /**
     * Get ambassador profile
     */
    public function profile()
    {
        $user = Auth::user();
        $ambassador = $user->ambassador;

        if (! $ambassador) {
            return $this->sendError('Ambassador profile not found.', [], 404);
        }

        // Load additional stats
        $ambassador->loadCount('referrals');
        $ambassador->loadSum('referrals as total_earnings', 'amount');

        return $this->sendResponse($ambassador, 'Ambassador profile retrieved successfully.');
    }

    /**
     * Apply to become an ambassador
     */
    public function apply(Request $request)
    {
        $request->validate([
            'reason' => 'required|string|min:50|max:1000',
            'experience' => 'nullable|string|max:1000',
            'social_media' => 'nullable|array',
            'social_media.*.platform' => 'required_with:social_media|string|max:50',
            'social_media.*.handle' => 'required_with:social_media|string|max:100',
            'agree_to_terms' => 'required|accepted',
        ]);

        $user = Auth::user();

        // Check if user already has an ambassador profile
        if ($user->ambassador) {
            return $this->sendError('You already have an ambassador profile.', [], 400);
        }

        // Check if user has an active subscription
        if (! $user->hasActiveSubscription()) {
            return $this->sendError('You need an active subscription to become an ambassador.', [], 400);
        }

        // Generate unique referral code
        $referralCode = $this->generateUniqueReferralCode();

        // Create ambassador profile
        $ambassador = Ambassador::create([
            'user_id' => $user->id,
            'referral_code' => $referralCode,
            'status' => 'pending',
            'reason' => $request->reason,
            'experience' => $request->experience,
            'social_media' => $request->social_media,
            'total_referrals' => 0,
            'active_referrals' => 0,
            'total_earnings' => 0,
            'current_month_referrals' => 0,
            'rank' => 0,
        ]);

        return $this->sendResponse($ambassador, 'Ambassador application submitted successfully. Your application will be reviewed within 24-48 hours.');
    }

    /**
     * Get ambassador leaderboard
     */
    public function leaderboard(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $limit = $request->get('limit', 20);

        $query = Ambassador::with('user')
            ->where('status', 'active')
            ->withCount('referrals')
            ->withSum('referrals as total_earnings', 'amount');

        // Filter by period
        if ($period === 'monthly') {
            $query->whereHas('referrals', function ($q) {
                $q->whereMonth('created_at', now()->month);
            });
        } elseif ($period === 'weekly') {
            $query->whereHas('referrals', function ($q) {
                $q->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            });
        } elseif ($period === 'daily') {
            $query->whereHas('referrals', function ($q) {
                $q->whereDate('created_at', now()->toDateString());
            });
        }

        $leaderboard = $query->orderBy('referrals_count', 'desc')
            ->limit($limit)
            ->get();

        return $this->sendResponse($leaderboard, 'Ambassador leaderboard retrieved successfully.');
    }

    /**
     * Generate referral code for ambassador
     */
    public function generateReferralCode()
    {
        $user = Auth::user();
        $ambassador = $user->ambassador;

        if (! $ambassador) {
            return $this->sendError('Ambassador profile not found.', [], 404);
        }

        if ($ambassador->status !== 'active') {
            return $this->sendError('Your ambassador account is not active.', [], 400);
        }

        // Check if ambassador already has a referral code
        if ($ambassador->referralCode) {
            return $this->sendResponse($ambassador->referralCode, 'You already have a referral code.');
        }

        // Generate unique referral code
        $code = $this->generateUniqueReferralCode();

        $referralCode = ReferralCode::create([
            'ambassador_id' => $ambassador->id,
            'code' => $code,
            'uses_count' => 0,
            'max_uses' => 100, // Default max uses
            'expires_at' => now()->addMonths(12), // Default 1 year expiration
        ]);

        return $this->sendResponse($referralCode, 'Referral code generated successfully.');
    }

    /**
     * Generate a unique referral code
     */
    private function generateUniqueReferralCode()
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (ReferralCode::where('code', $code)->exists());

        return $code;
    }

    /**
     * Get ambassador statistics
     */
    public function stats()
    {
        $user = Auth::user();
        $ambassador = $user->ambassador;

        if (! $ambassador) {
            return $this->sendError('Ambassador profile not found.', [], 404);
        }

        $stats = [
            'total_referrals' => $ambassador->referrals()->count(),
            'successful_referrals' => $ambassador->referrals()->where('status', 'completed')->count(),
            'pending_referrals' => $ambassador->referrals()->where('status', 'pending')->count(),
            'total_earnings' => $ambassador->referrals()->where('status', 'completed')->sum('amount'),
            'referral_code' => $ambassador->referralCode,
            'referral_code_uses' => $ambassador->referralCode ? $ambassador->referralCode->uses_count : 0,
            'referral_code_max_uses' => $ambassador->referralCode ? $ambassador->referralCode->max_uses : 0,
            'referral_code_expires_at' => $ambassador->referralCode ? $ambassador->referralCode->expires_at : null,
        ];

        return $this->sendResponse($stats, 'Ambassador statistics retrieved successfully.');
    }
}
