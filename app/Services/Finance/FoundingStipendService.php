<?php

namespace App\Services\Finance;

use App\Models\Payout;
use App\Models\Therapist;
use App\Services\FCMService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Founding therapist stipend: N150,000/month guaranteed minimum for 3 months post-launch.
 * Runs on the 1st of each month via the scheduler.
 */
class FoundingStipendService
{
    private const STIPEND_AMOUNT = 150000;
    private const STIPEND_CURRENCY = 'NGN';
    private const MAX_STIPEND_MONTHS = 3;

    /**
     * Process stipends for all eligible founding therapists.
     * Idempotent: skips therapists who already received a stipend this calendar month.
     */
    public function processMonthlyStipends(): array
    {
        $processed = 0;
        $skipped = 0;
        $errors = [];

        $eligible = Therapist::where('is_founding', true)
            ->where('stipend_eligible', true)
            ->where('stipend_months_paid', '<', self::MAX_STIPEND_MONTHS)
            ->where('is_verified', true)
            ->where('is_accepting_clients', true)
            ->with('user')
            ->get();

        foreach ($eligible as $therapist) {
            try {
                // Idempotency: only one stipend payout per therapist per calendar month
                $alreadyPaid = Payout::where('user_id', $therapist->user_id)
                    ->where('type', 'stipend')
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->exists();

                if ($alreadyPaid) {
                    $skipped++;
                    continue;
                }

                // Calculate top-up: if therapist earned >= stipend already, skip
                $monthStr = now()->format('Y-m');
                $earned = $this->getMonthlyEarnings($therapist->user_id, $monthStr);

                if ($earned >= self::STIPEND_AMOUNT) {
                    $skipped++;
                    Log::info('FoundingStipendService: earnings exceed stipend, skipping', [
                        'user_id' => $therapist->user_id,
                        'earned' => $earned,
                    ]);
                    continue;
                }

                $topUp = self::STIPEND_AMOUNT - $earned;

                Payout::create([
                    'user_id'        => $therapist->user_id,
                    'amount'         => $topUp,
                    'currency'       => self::STIPEND_CURRENCY,
                    'type'           => 'stipend',
                    'status'         => 'pending',
                    'bank_name'      => $therapist->user?->bank_name ?? 'UNKNOWN',
                    'account_number' => $therapist->user?->account_number ?? 'UNKNOWN',
                    'account_name'   => trim(($therapist->user?->first_name ?? '') . ' ' . ($therapist->user?->last_name ?? '')),
                    'description'    => 'Founding therapist stipend — month ' . ($therapist->stipend_months_paid + 1) . ' of ' . self::MAX_STIPEND_MONTHS,
                ]);

                $therapist->increment('stipend_months_paid');

                // Disable stipend if max months reached
                if ($therapist->stipend_months_paid >= self::MAX_STIPEND_MONTHS) {
                    $therapist->stipend_eligible = false;
                    $therapist->save();
                }

                $this->notifyTherapist($therapist->user_id, $topUp);
                $processed++;

            } catch (\Throwable $e) {
                Log::error('FoundingStipendService: error processing stipend', [
                    'user_id' => $therapist->user_id ?? null,
                    'error'   => $e->getMessage(),
                ]);
                $errors[] = ['user_id' => $therapist->user_id, 'error' => $e->getMessage()];
            }
        }

        Log::info('FoundingStipendService: monthly run complete', [
            'processed' => $processed,
            'skipped'   => $skipped,
            'errors'    => count($errors),
        ]);

        return compact('processed', 'skipped', 'errors');
    }

    private function getMonthlyEarnings(int $userId, string $month): float
    {
        [$y, $m] = explode('-', $month);
        $start = Carbon::createFromDate((int) $y, (int) $m, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        return (float) Payout::where('user_id', $userId)
            ->whereIn('status', ['completed', 'processing'])
            ->whereIn('type', ['session', 'earnings'])
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');
    }

    private function notifyTherapist(int $userId, float $amount): void
    {
        try {
            app(FCMService::class)->sendToUser($userId, [
                'title' => 'Founding Stipend Credited',
                'body'  => '₦' . number_format($amount, 0) . ' founding stipend has been queued for your account. Thank you for being part of our founding team!',
                'data'  => ['type' => 'stipend_credited'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('FoundingStipendService: FCM notification failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
