<?php

namespace App\Http\Controllers\API\V1\Therapist;

use App\Http\Controllers\API\BaseController;
use App\Models\TherapistPayout;
use App\Models\TherapySession;
use App\Services\TherapistCompensationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EarningsController extends BaseController
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Resolve the therapist's current keep-percent from the rate-based tier system
        // stored in settings (group=commission, key=tiers). This is used only for the
        // display summary; per-session commission_amount is already stored on each row.
        $profile        = \App\Models\TherapistProfile::where('user_id', $user->id)->first();
        $hourlyRate     = (float) ($profile?->hourly_rate ?? 0);
        $currency       = ($profile?->country_of_operation ?? '') === 'NG' ? 'NGN' : 'USD';
        $compensation   = new \App\Services\TherapistCompensationService();
        // therapistKeepPercent expects a TherapistProfile (named $therapist in the service)
        $currentKeepPct = $compensation->therapistKeepPercent($hourlyRate, $profile ?? null, $currency);
        $commissionRate = (1 - ($currentKeepPct / 100));
        $therapistShare = $currentKeepPct / 100;

        // 1. Calculate Total Earnings — use stored commission_amount when available,
        //    fall back to (session_rate * therapist_share) for legacy sessions.
        $baseQuery = TherapySession::where('therapist_id', $user->id)
            ->where('status', 'completed')
            ->where('payment_status', 'paid');

        $totalEarnings = (clone $baseQuery)
            ->sum(DB::raw("COALESCE(commission_amount, session_rate * {$therapistShare})"));

        $monthlyEarnings = (clone $baseQuery)
            ->whereYear('ended_at', now()->year)
            ->whereMonth('ended_at', now()->month)
            ->sum(DB::raw("COALESCE(commission_amount, session_rate * {$therapistShare})"));

        // 2. Gross revenue (what patients paid in full)
        $grossEarnings = (clone $baseQuery)->sum('session_rate');

        // 3. Commission deducted = gross minus net therapist take-home
        $commissionDeducted = max(0, $grossEarnings - $totalEarnings);

        // 4. Total Paid Out (all non-cancelled payout records)
        $totalPaidOut = TherapistPayout::where('therapist_id', $user->id)
            ->whereIn('status', ['completed', 'paid', 'processing', 'pending'])
            ->sum('amount');

        // 5. Pending Balance (Available for withdrawal)
        $pendingPayout = max(0, $totalEarnings - $totalPaidOut);

        // 6. Payout History
        $payouts = TherapistPayout::where('therapist_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $data = [
            'total_earnings'      => $totalEarnings,
            'this_month_earnings' => $monthlyEarnings,
            'pending_payout'      => $pendingPayout,
            'currency'            => 'NGN',
            'gross_earnings'      => $grossEarnings,
            'commission_deducted' => $commissionDeducted,
            'net_earnings'        => $totalEarnings,
            'commission_rate'     => round($commissionRate * 100, 1),
            'therapist_share'     => round($currentKeepPct, 1),
            'payment_history'     => $payouts,
        ];

        return $this->sendResponse($data, 'Earnings data retrieved successfully.');
    }

    public function preview(Request $request)
    {
        $request->validate([
            'hourly_rate' => 'required|numeric|min:0',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Therapist model (therapist_profiles row via the Therapist model)
        $therapistModel = $user->therapist ?? null;

        // TherapistProfile is the secondary profile model (simpler, used here for payout_currency)
        $therapistProfile = $user->therapistProfile ?? null;
        $currency = $therapistProfile?->payout_currency ?? ($therapistModel?->payout_currency ?? 'NGN');

        $service = new TherapistCompensationService();
        $preview = $service->getEarningsPreview(
            (float) $request->input('hourly_rate'),
            $currency,
            $therapistModel
        );

        return response()->json([
            'success' => true,
            'data'    => $preview,
        ]);
    }

    public function payouts(Request $request)
    {
        $payouts = TherapistPayout::where('therapist_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->sendResponse($payouts, 'Payout history retrieved.');
    }

    public function requestPayout(Request $request)
    {
        $user = $request->user();

        // Check bank account before acquiring lock
        $profile = $user->therapist;
        if (! $profile || ! $profile->account_number) {
            return $this->sendError('Please add your bank account details first in Settings.');
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $user) {
            // Lock all existing payouts for this therapist to prevent concurrent duplicate requests
            TherapistPayout::where('therapist_id', $user->id)->lockForUpdate()->get();

            // Re-calculate available balance inside the lock
            $commissionRate = (float) config('onwynd.commission_rate', 0.20);
            $therapistShare = 1 - $commissionRate;

            $totalEarnings = TherapySession::where('therapist_id', $user->id)
                ->where('status', 'completed')
                ->where('payment_status', 'paid')
                ->sum(DB::raw("COALESCE(commission_amount, session_rate * {$therapistShare})"));

            $totalPaidOut = TherapistPayout::where('therapist_id', $user->id)
                ->whereIn('status', ['completed', 'paid', 'processing', 'pending'])
                ->sum('amount');

            $availableBalance = max(0, $totalEarnings - $totalPaidOut);

            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:1000|max:'.$availableBalance,
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation Error.', $validator->errors(), 422);
            }

            $payout = TherapistPayout::create([
                'therapist_id'   => $user->id,
                'amount'         => $request->amount,
                'currency'       => 'NGN',
                'status'         => 'pending',
                'payment_reason' => 'Withdrawal Request',
                'initiated_at'   => now(),
            ]);

            \Illuminate\Support\Facades\Log::info('Payout requested', [
                'therapist_id'     => $user->id,
                'amount'           => $request->amount,
                'available_balance'=> $availableBalance,
                'payout_id'        => $payout->id,
            ]);

            return $this->sendResponse($payout, 'Payout requested successfully.');
        });
    }
}
