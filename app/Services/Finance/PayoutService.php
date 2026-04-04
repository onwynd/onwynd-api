<?php

namespace App\Services\Finance;

use App\Models\Payout;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\FCMService;
use App\Services\PaymentService\PaystackService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayoutService
{
    public function calculateMonthlyEarnings(int $therapistId, string $month): array
    {
        [$y, $m] = explode('-', $month);
        $start = now()->setYear((int) $y)->setMonth((int) $m)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $sessions = TherapySession::where('therapist_id', $therapistId)
            ->where('status', 'completed')
            ->whereNotNull('ended_at')
            ->whereBetween('ended_at', [$start, $end])
            ->get(['id', 'session_rate', 'commission_amount', 'currency']);

        // Use stored commission_amount (set by TherapistCompensationService on session completion)
        // Fall back to 80% for legacy sessions that pre-date the tiered commission system
        $earnings = $sessions->sum(fn ($s) => $s->commission_amount ?? (($s->session_rate ?? 0) * 0.80));
        $currency = $sessions->first()->currency ?? 'NGN';

        return [
            'therapist_id' => $therapistId,
            'month' => $month,
            'earnings' => $earnings,
            'currency' => $currency,
            'session_count' => $sessions->count(),
        ];
    }

    public function generatePayoutBatch(string $month): array
    {
        [$y, $m] = explode('-', $month);
        $start = now()->setYear((int) $y)->setMonth((int) $m)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $therapistIds = TherapySession::where('status', 'completed')
            ->whereBetween('ended_at', [$start, $end])
            ->pluck('therapist_id')
            ->unique();

        $batch = [];
        foreach ($therapistIds as $tid) {
            $summary = $this->calculateMonthlyEarnings($tid, $month);
            if ($summary['earnings'] > 0) {
                $user = User::find($tid);
                $payout = Payout::create([
                    'user_id' => $tid,
                    'amount' => $summary['earnings'],
                    'currency' => $summary['currency'],
                    'status' => 'pending',
                    'bank_name' => $user->bank_name ?? 'UNKNOWN',
                    'account_number' => $user->account_number ?? 'UNKNOWN',
                    'account_name' => ($user->first_name.' '.$user->last_name),
                ]);
                $batch[] = $payout;
            }
        }

        return [
            'month' => $month,
            'count' => count($batch),
            'payouts' => array_map(fn ($p) => [
                'id' => $p->id,
                'user_id' => $p->user_id,
                'amount' => $p->amount,
                'currency' => $p->currency,
                'status' => $p->status,
            ], $batch),
        ];
    }

    public function initiateBankTransfer(int $payoutId): array
    {
        $payout = Payout::findOrFail($payoutId);

        if (in_array($payout->status, ['completed', 'processing'])) {
            return ['success' => false, 'message' => 'Payout already ' . $payout->status];
        }

        $user = User::find($payout->user_id);
        if (! $user) {
            return ['success' => false, 'message' => 'Therapist user not found'];
        }

        $paystack = app(PaystackService::class);

        // Ensure therapist has a Paystack recipient code
        $recipientCode = $user->paystack_recipient_code ?? null;
        if (! $recipientCode) {
            if (! $user->account_number || ! $user->bank_code) {
                // Queue a notification to collect bank details and abort
                Log::warning('Payout blocked: therapist missing bank details', [
                    'user_id' => $user->id,
                    'payout_id' => $payoutId,
                ]);
                $payout->update(['status' => 'blocked', 'failure_reason' => 'Missing bank details — therapist must add account in Settings']);

                return ['success' => false, 'message' => 'Therapist has not added bank account details. Payout queued for when details are provided.'];
            }

            $result = $paystack->createTransferRecipient(
                accountNumber: $user->account_number,
                bankCode: $user->bank_code,
                accountName: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            );

            if (! $result['success']) {
                Log::error('Failed to create Paystack transfer recipient', ['user_id' => $user->id, 'message' => $result['message'] ?? '']);

                return ['success' => false, 'message' => 'Could not register bank account with Paystack: ' . ($result['message'] ?? 'Unknown error')];
            }

            $recipientCode = $result['recipient_code'];
            $user->paystack_recipient_code = $recipientCode;
            $user->save();
        }

        $reference = 'ONWYND_PAYOUT_' . strtoupper(Str::random(12));
        $amountKobo = (int) round($payout->amount * 100);

        $transfer = $paystack->initiateTransfer(
            amountKobo: $amountKobo,
            recipientCode: $recipientCode,
            reference: $reference,
            reason: 'Onwynd session payout — payout #' . $payout->id,
        );

        if (! $transfer['success']) {
            Log::error('Paystack transfer initiation failed', ['payout_id' => $payoutId, 'message' => $transfer['message'] ?? '']);
            $payout->update(['status' => 'failed', 'failure_reason' => $transfer['message'] ?? 'Transfer initiation failed']);

            return ['success' => false, 'message' => $transfer['message'] ?? 'Transfer initiation failed'];
        }

        $payout->update([
            'status' => 'processing',
            'reference' => $reference,
            'transfer_code' => $transfer['transfer_code'] ?? null,
            'processed_at' => now(),
        ]);

        Log::info('Paystack transfer initiated', [
            'payout_id' => $payoutId,
            'transfer_code' => $transfer['transfer_code'] ?? null,
            'reference' => $reference,
        ]);

        $this->sendPayoutNotification($payout->user_id, $payout->amount);

        return [
            'success' => true,
            'reference' => $reference,
            'transfer_code' => $transfer['transfer_code'] ?? null,
            'status' => 'processing',
        ];
    }

    public function sendPayoutNotification(int $therapistId, float $amount = 0): void
    {
        try {
            $fcm = app(FCMService::class);
            $fcm->sendToUser($therapistId, [
                'title' => 'Payout Initiated',
                'body' => 'Your payout of ₦' . number_format($amount, 2) . ' is being processed and will arrive in your bank account within 1–2 business days.',
                'data' => ['type' => 'payout_initiated'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('PayoutService: FCM notification failed', [
                'therapist_id' => $therapistId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
