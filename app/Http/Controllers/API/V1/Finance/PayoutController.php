<?php

namespace App\Http\Controllers\API\V1\Finance;

use App\Http\Controllers\API\BaseController;
use App\Models\Payout;
use App\Models\TherapistProfile;
use App\Services\Finance\PayoutService;
use App\Services\PaymentService\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PayoutController extends BaseController
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Payout::with('user:id,first_name,last_name,email');

        // Therapists see only their own payouts; admins see all
        if ($user && $user->hasRole('therapist')) {
            $query->where('user_id', $user->id);
        } elseif ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $payouts = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->sendResponse($payouts, 'Payouts retrieved successfully.');
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:100',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // Load therapist profile for bank details
        $profile = TherapistProfile::where('user_id', $user->id)->first();

        if (! $profile || ! $profile->account_number || ! $profile->bank_code) {
            return $this->sendError('Bank account details not set. Please save your bank account first.', [], 422);
        }

        $payout = Payout::create([
            'user_id' => $user->id,
            'amount' => $request->amount,
            'currency' => 'NGN',
            'status' => 'pending',
            'bank_name' => $profile->bank_name ?? '',
            'account_number' => $profile->account_number,
            'account_name' => $profile->account_name ?? $user->first_name.' '.$user->last_name,
        ]);

        return $this->sendResponse($payout, 'Payout request created successfully.');
    }

    public function process(Request $request, $id)
    {
        $payout = Payout::find($id);

        if (! $payout) {
            return $this->sendError('Payout not found.');
        }

        if ($payout->status !== 'pending') {
            return $this->sendError('Payout is not in pending state.');
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject',
            'reason' => 'required_if:action,reject|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        try {
            if ($request->action === 'approve') {
                $transferResult = $this->triggerTransfer($payout);

                if ($transferResult['success']) {
                    $payout->status = 'processing';
                    $payout->reference = $transferResult['reference'] ?? null;
                    $payout->transfer_code = $transferResult['transfer_code'] ?? null;
                    $payout->processed_at = now();
                } else {
                    $payout->status = 'failed';
                    $payout->failure_reason = $transferResult['message'];
                }
            } else {
                $payout->status = 'rejected';
                $payout->failure_reason = $request->reason;
            }

            $payout->save();

            return $this->sendResponse($payout, 'Payout processed successfully.');
        } catch (\Exception $e) {
            Log::error('Payout processing error', ['payout_id' => $id, 'message' => $e->getMessage()]);

            return $this->sendError('Error processing payout.', ['error' => $e->getMessage()]);
        }
    }

    public function batch(Request $request)
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $service = new PayoutService;
        $result = $service->generatePayoutBatch($request->string('month'));

        return $this->sendResponse($result, 'Payout batch generated');
    }

    /**
     * Trigger a real Paystack transfer for the given payout.
     * If the therapist does not yet have a recipient_code, one is created first
     * using their stored bank account details.
     */
    private function triggerTransfer(Payout $payout): array
    {
        $paystack = app(PaystackService::class);
        $user = $payout->user;

        if (! $user) {
            return ['success' => false, 'message' => 'Payout user not found'];
        }

        try {
            $profile = TherapistProfile::where('user_id', $user->id)->first();

            // Step 1: Ensure recipient_code exists on profile
            $recipientCode = $profile?->recipient_code ?? null;

            if (! $recipientCode) {
                if (! $profile || ! $profile->account_number || ! $profile->bank_code) {
                    return ['success' => false, 'message' => 'Therapist bank account details are incomplete'];
                }

                $recipientResult = $paystack->createTransferRecipient(
                    $profile->account_number,
                    $profile->bank_code,
                    $profile->account_name ?? ($user->first_name.' '.$user->last_name),
                    $payout->currency ?? 'NGN'
                );

                if (! $recipientResult['success']) {
                    return $recipientResult;
                }

                $recipientCode = $recipientResult['recipient_code'];
                $profile->recipient_code = $recipientCode;
                $profile->save();
            }

            // Step 2: Initiate the transfer
            $amountKobo = (int) ($payout->amount * 100);
            $reference = 'ONWYND_PAY_'.strtoupper(Str::random(12)).'_'.$payout->id;
            $reason = "Therapist earnings payout  Onwynd (Payout #{$payout->id})";

            $transferResult = $paystack->initiateTransfer($amountKobo, $recipientCode, $reference, $reason);

            if ($transferResult['success']) {
                $payout->reference = $reference;
                $payout->transfer_code = $transferResult['transfer_code'] ?? null;
                $payout->save();
            }

            return $transferResult;
        } catch (\Throwable $e) {
            Log::error('triggerTransfer exception', ['payout_id' => $payout->id, 'message' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
