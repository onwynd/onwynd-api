<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Ambassador;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Http\Request;

class AdminReferralController extends BaseController
{
    /**
     * Get all referrals with filtering and pagination
     */
    public function index(Request $request)
    {
        $query = Referral::with(['ambassador.user', 'referredUser', 'plan'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Filter by ambassador
        if ($request->has('ambassador_id')) {
            $query->where('ambassador_id', $request->ambassador_id);
        }

        // Search by user name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('referredUser', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $referrals = $query->paginate($request->get('per_page', 20));

        return $this->sendResponse($referrals, 'Referrals retrieved successfully.');
    }

    /**
     * Get referral statistics for admin dashboard
     */
    public function stats(Request $request)
    {
        $stats = [
            'total_referrals' => Referral::count(),
            'pending_referrals' => Referral::where('status', 'pending')->count(),
            'completed_referrals' => Referral::where('status', 'completed')->count(),
            'cancelled_referrals' => Referral::where('status', 'cancelled')->count(),
            'total_ambassadors' => Ambassador::where('status', 'active')->count(),
            'total_earnings' => Referral::where('status', 'completed')->sum('amount') ?? 0,
            'monthly_stats' => [
                'current_month_referrals' => Referral::whereMonth('created_at', now()->month)->count(),
                'last_month_referrals' => Referral::whereMonth('created_at', now()->subMonth()->month)->count(),
                'current_month_earnings' => Referral::whereMonth('created_at', now()->month)->sum('amount') ?? 0,
                'last_month_earnings' => Referral::whereMonth('created_at', now()->subMonth()->month)->sum('amount') ?? 0,
            ],
        ];

        return $this->sendResponse($stats, 'Referral statistics retrieved successfully.');
    }

    /**
     * Get all ambassadors with their referral stats
     */
    public function ambassadors(Request $request)
    {
        $query = Ambassador::with('user')
            ->withCount('referrals')
            ->withSum('referrals as total_earnings', 'amount')
            ->orderBy('referrals_count', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by user name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $ambassadors = $query->paginate($request->get('per_page', 20));

        return $this->sendResponse($ambassadors, 'Ambassadors retrieved successfully.');
    }

    /**
     * Get a single referral with details
     */
    public function show($id)
    {
        $referral = Referral::with(['ambassador.user', 'referredUser', 'plan'])
            ->find($id);

        if (! $referral) {
            return $this->sendError('Referral not found.', [], 404);
        }

        return $this->sendResponse($referral, 'Referral retrieved successfully.');
    }

    /**
     * Update referral status
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,completed,cancelled',
            'amount' => 'nullable|numeric|min:0',
        ]);

        $referral = Referral::find($id);

        if (! $referral) {
            return $this->sendError('Referral not found.', [], 404);
        }

        $referral->update([
            'status' => $request->status,
            'amount' => $request->amount ?? $referral->amount,
        ]);

        // If status is completed, create a reward for the ambassador
        if ($request->status === 'completed') {
            $this->createRewardForReferral($referral);
        }

        return $this->sendResponse($referral, 'Referral status updated successfully.');
    }

    /**
     * Get referral rewards with filtering
     */
    public function rewards(Request $request)
    {
        $query = ReferralReward::with(['user', 'referral'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $rewards = $query->paginate($request->get('per_page', 20));

        return $this->sendResponse($rewards, 'Referral rewards retrieved successfully.');
    }

    /**
     * Update reward status
     */
    public function updateRewardStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,paid,expired',
        ]);

        $reward = ReferralReward::find($id);

        if (! $reward) {
            return $this->sendError('Reward not found.', [], 404);
        }

        $reward->update([
            'status' => $request->status,
            'redeemed_at' => $request->status === 'paid' ? now() : $reward->redeemed_at,
        ]);

        return $this->sendResponse($reward, 'Reward status updated successfully.');
    }

    /**
     * Get referral leaderboard for admin
     */
    public function leaderboard(Request $request)
    {
        $period = $request->get('period', 'all_time');
        $limit = $request->get('limit', 50);

        $query = Ambassador::with('user')
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

        return $this->sendResponse($leaderboard, 'Referral leaderboard retrieved successfully.');
    }

    /**
     * Create a reward for a completed referral
     */
    private function createRewardForReferral($referral)
    {
        if (! $referral->amount || $referral->amount <= 0) {
            return;
        }

        ReferralReward::create([
            'user_id' => $referral->ambassador->user_id,
            'referral_id' => $referral->id,
            'amount' => $referral->amount,
            'currency' => 'USD',
            'type' => 'referral_bonus',
            'status' => 'pending',
            'issued_at' => now(),
            'expires_at' => now()->addMonths(12),
        ]);
    }
}
