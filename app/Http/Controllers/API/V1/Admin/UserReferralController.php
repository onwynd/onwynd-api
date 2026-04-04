<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\ReferralRewardConfig;
use App\Models\UserReferral;
use App\Models\UserReferralCode;
use Illuminate\Http\Request;

class UserReferralController extends BaseController
{
    /**
     * List all user-to-user referrals with filtering.
     * GET /api/v1/admin/user-referrals
     */
    public function index(Request $request)
    {
        $query = UserReferral::with(['referrer', 'referee', 'referralCode'])
            ->orderByDesc('created_at');

        if ($request->filled('tier')) {
            $query->where('referrer_tier', $request->tier);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('referrer', fn ($r) => $r->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%"))
                    ->orWhereHas('referee', fn ($r) => $r->where('email', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $referrals = $query->paginate($request->get('per_page', 20));

        return $this->sendResponse($referrals, 'User referrals retrieved.');
    }

    /**
     * Aggregate stats across all user referral tracks.
     * GET /api/v1/admin/user-referrals/stats
     */
    public function stats()
    {
        return $this->sendResponse([
            'total'            => UserReferral::count(),
            'by_tier'          => UserReferral::selectRaw('referrer_tier, count(*) as count')
                ->groupBy('referrer_tier')->pluck('count', 'referrer_tier'),
            'by_status'        => UserReferral::selectRaw('status, count(*) as count')
                ->groupBy('status')->pluck('count', 'status'),
            'this_month'       => UserReferral::whereMonth('created_at', now()->month)->count(),
            'last_month'       => UserReferral::whereMonth('created_at', now()->subMonth()->month)->count(),
            'active_codes'     => UserReferralCode::where('is_active', true)->count(),
            'freemium_codes'   => UserReferralCode::where('tier', 'freemium')->count(),
            'paid_codes'       => UserReferralCode::where('tier', 'paid')->count(),
        ], 'User referral stats retrieved.');
    }

    /**
     * List all reward configs.
     * GET /api/v1/admin/referral-reward-configs
     */
    public function configs()
    {
        return $this->sendResponse(
            ReferralRewardConfig::orderBy('referrer_tier')->get(),
            'Referral reward configs retrieved.'
        );
    }

    /**
     * Update a reward config (admin toggle / value change).
     * PUT /api/v1/admin/referral-reward-configs/{id}
     */
    public function updateConfig(Request $request, $id)
    {
        $config = ReferralRewardConfig::findOrFail($id);

        $request->validate([
            'reward_value'     => 'sometimes|numeric|min:0',
            'is_enabled'       => 'sometimes|boolean',
            'max_discount_cap' => 'sometimes|nullable|numeric|min:0|max:100',
            'notes'            => 'sometimes|nullable|string|max:255',
        ]);

        $config->update($request->only(['reward_value', 'is_enabled', 'max_discount_cap', 'notes']));

        return $this->sendResponse($config->fresh(), 'Reward config updated.');
    }
}
