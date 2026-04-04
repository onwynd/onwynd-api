<?php

namespace App\Http\Controllers\API\V1\Ambassador;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends BaseController
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $ambassador = $user->ambassador;

        if (! $ambassador) {
            return $this->sendError('Ambassador profile not found.', [], 404);
        }

        $referralCount = $ambassador->referrals()->count();
        $activeReferrals = $ambassador->referrals()->where('status', 'completed')->count(); // Assuming 'completed' means successful referral
        $totalEarnings = $ambassador->referrals()->where('status', 'paid')->sum('amount'); // Assuming 'amount' is commission earned

        $recentReferrals = $ambassador->referrals()
            ->with('referredUser:id,first_name,last_name,created_at')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return $this->sendResponse([
            'referral_code' => $ambassador->referral_code,
            'stats' => [
                'total_referrals' => $referralCount,
                'active_referrals' => $activeReferrals,
                'total_earnings' => $totalEarnings,
            ],
            'recent_referrals' => $recentReferrals,
        ], 'Ambassador dashboard data retrieved.');
    }
}
