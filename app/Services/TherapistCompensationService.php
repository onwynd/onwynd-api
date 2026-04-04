<?php

namespace App\Services;

use App\Models\GroupSession;
use App\Models\TherapistPayout;
use App\Models\TherapySession;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TherapistCompensationService
{
    // Base value of a session when paid via subscription (in NGN).
    // Apply tiered keep percentage to this base.
    const SUBSCRIPTION_SESSION_BASE_VALUE = 5000;

    /**
     * Calculate the commission amount for a given session.
     */
    protected function settings(): array
    {
        // Read commission settings from DB
        $rows = \App\Models\Setting::where('group', 'commission')->get();
        $map = [];
        foreach ($rows as $r) {
            $map[$r->key] = $r->value;
        }
        $tiers = isset($map['tiers']) ? json_decode($map['tiers'], true) : null;
        if (! is_array($tiers) || empty($tiers)) {
            $tiers = [
                ['min' => 1, 'max' => 5000, 'therapist_keep_percent' => 90],
                ['min' => 5001, 'max' => 10000, 'therapist_keep_percent' => 85],
                ['min' => 10001, 'max' => 20000, 'therapist_keep_percent' => 82],
                ['min' => 20001, 'max' => null, 'therapist_keep_percent' => 80],
            ];
        }
        $foundingDiscount = isset($map['founding_discount_percent']) ? (float) $map['founding_discount_percent'] : 3.0;
        $foundingDuration = isset($map['founding_duration_months']) ? (int) $map['founding_duration_months'] : 24;

        return ['tiers' => $tiers, 'founding_discount_percent' => $foundingDiscount, 'founding_duration_months' => $foundingDuration];
    }

    public function therapistKeepPercent(float $rate, ?\App\Models\Therapist $therapist = null, string $currency = 'NGN'): float
    {
        $cfg = $this->settings();
        $tiers = $cfg['tiers'];

        // Support both old flat array format and new currency-keyed format
        if (isset($tiers['NGN'])) {
            $tiers = $tiers[$currency] ?? $tiers['NGN'];
        }

        $keep = 80.0;
        foreach ($tiers as $tier) {
            $min = (float) ($tier['min'] ?? 0);
            $max = $tier['max'] === null ? null : (float) $tier['max'];
            if ($rate >= $min && ($max === null || $rate <= $max)) {
                $keep = (float) ($tier['therapist_keep_percent'] ?? $keep);
                break;
            }
        }
        if ($therapist && ($therapist->is_founding ?? false) && (($cfg['founding_enabled'] ?? true) === true)) {
            $started = $therapist->founding_started_at ?? null;
            if ($started) {
                $deadline = (clone $started)->addMonths($cfg['founding_duration_months'] ?? 24);
                if (now()->lessThanOrEqualTo($deadline)) {
                    $keep += (float) ($cfg['founding_discount_percent'] ?? 3.0);
                }
            }
        }

        return max(0.0, min(100.0, $keep));
    }

    /**
     * Return a structured earnings preview for a given hourly rate and currency.
     * Covers 30-min, 35-min, and 60-min session durations.
     *
     * @param  float  $hourlyRate  The therapist's hourly rate in the given currency.
     * @param  string  $currency  'NGN' or 'USD'.
     * @param  \App\Models\Therapist|null  $therapistModel  Used to detect founding status.
     */
    public function getEarningsPreview(float $hourlyRate, string $currency = 'NGN', ?\App\Models\Therapist $therapistModel = null): array
    {
        $cfg = $this->settings();
        $currencySymbol = $currency === 'USD' ? '$' : '₦';

        $durations = [
            ['minutes' => 30, 'factor' => 0.5],
            ['minutes' => 35, 'factor' => 35 / 60],
            ['minutes' => 60, 'factor' => 1.0],
        ];

        $isFounding = false;
        if ($therapistModel && ($therapistModel->is_founding ?? false)) {
            $started = $therapistModel->founding_started_at ?? null;
            if ($started) {
                $deadline = (clone $started)->addMonths($cfg['founding_duration_months'] ?? 24);
                if (now()->lessThanOrEqualTo($deadline)) {
                    $isFounding = true;
                }
            }
        }

        $rows = [];
        foreach ($durations as $dur) {
            $sessionFee = round($hourlyRate * $dur['factor'], 2);

            // Standard keep (no founding bonus)
            $standardKeepPct = $this->therapistKeepPercentRaw($sessionFee, $currency);
            $standardCommissionRate = 1.0 - ($standardKeepPct / 100.0);
            $standardCommissionAmount = round($sessionFee * $standardCommissionRate, 2);
            $standardNet = round($sessionFee * ($standardKeepPct / 100.0), 2);

            // Founding keep (with founding bonus)
            $foundingBonus = (float) ($cfg['founding_discount_percent'] ?? 3.0);
            $foundingKeepPct = min(100.0, $standardKeepPct + $foundingBonus);
            $foundingCommissionRate = 1.0 - ($foundingKeepPct / 100.0);
            $foundingCommissionAmount = round($sessionFee * $foundingCommissionRate, 2);
            $foundingNet = round($sessionFee * ($foundingKeepPct / 100.0), 2);

            // Resolve the tier label for this session fee
            $tierLabel = $this->resolveTierLabel($sessionFee, $currency);

            $rows[] = [
                'minutes'                   => $dur['minutes'],
                'session_fee'               => $sessionFee,
                'commission_rate'           => $standardCommissionRate,
                'commission_amount'         => $standardCommissionAmount,
                'therapist_net'             => $standardNet,
                'founding_commission_rate'  => $foundingCommissionRate,
                'founding_commission_amount'=> $foundingCommissionAmount,
                'founding_therapist_net'    => $foundingNet,
                'tier_label'               => $tierLabel,
            ];
        }

        return [
            'currency'        => $currency,
            'currency_symbol' => $currencySymbol,
            'hourly_rate'     => $hourlyRate,
            'is_founding'     => $isFounding,
            'durations'       => $rows,
            'note'            => 'Lower rates attract more bookings and reviews — and keep more of each session in your pocket.',
        ];
    }

    /**
     * Resolve only the keep percent from tiers WITHOUT applying the founding bonus.
     * Used internally by getEarningsPreview().
     */
    private function therapistKeepPercentRaw(float $rate, string $currency = 'NGN'): float
    {
        $cfg = $this->settings();
        $tiers = $cfg['tiers'];

        if (isset($tiers['NGN'])) {
            $tiers = $tiers[$currency] ?? $tiers['NGN'];
        }

        $keep = 80.0;
        foreach ($tiers as $tier) {
            $min = (float) ($tier['min'] ?? 0);
            $max = $tier['max'] === null ? null : (float) $tier['max'];
            if ($rate >= $min && ($max === null || $rate <= $max)) {
                $keep = (float) ($tier['therapist_keep_percent'] ?? $keep);
                break;
            }
        }

        return max(0.0, min(100.0, $keep));
    }

    /**
     * Resolve the human-readable tier label for a given session fee and currency.
     */
    private function resolveTierLabel(float $rate, string $currency = 'NGN'): string
    {
        $cfg = $this->settings();
        $tiers = $cfg['tiers'];

        if (isset($tiers['NGN'])) {
            $tiers = $tiers[$currency] ?? $tiers['NGN'];
        }

        foreach ($tiers as $tier) {
            $min = (float) ($tier['min'] ?? 0);
            $max = $tier['max'] === null ? null : (float) $tier['max'];
            if ($rate >= $min && ($max === null || $rate <= $max)) {
                return $tier['label'] ?? '';
            }
        }

        return '';
    }

    public function calculateCommission(TherapySession $session): float
    {
        $rate = $session->payment_method === 'subscription'
            ? self::SUBSCRIPTION_SESSION_BASE_VALUE
            : (float) $session->session_rate;
        $therapistModel = $session->therapist?->therapist ?? null; // Therapist (therapist_profiles)
        $keepPercent = $this->therapistKeepPercent($rate, $therapistModel);

        return $rate * ($keepPercent / 100.0);
    }

    /**
     * T.1: Calculate commission for Group Session.
     */
    public function calculateGroupCommission(GroupSession $session): float
    {
        $therapistUser = $session->therapist;
        $therapistModel = $therapistUser?->therapistProfile ?? null;

        // Corporate/University: Platform pays standard 1:1 rate (T.2)
        if (in_array($session->session_type, ['corporate', 'university'])) {
            $rate = (float) ($therapistModel->session_rate ?? self::SUBSCRIPTION_SESSION_BASE_VALUE);
            $keepPercent = $this->therapistKeepPercent($rate, $therapistModel);

            return $rate * ($keepPercent / 100.0);
        }

        // Open/Couple: Commission on total revenue (price_per_seat * seats filled)
        $seatsFilled = $session->participants()->where('invite_status', 'accepted')->count();
        $totalRevenue = ($session->price_per_seat_kobo / 100.0) * $seatsFilled;

        // We use the per-seat price to determine the commission tier
        $perSeatPrice = $session->price_per_seat_kobo / 100.0;
        $keepPercent = $this->therapistKeepPercent($perSeatPrice, $therapistModel);

        return $totalRevenue * ($keepPercent / 100.0);
    }

    /**
     * Process the completion of a session: calculate commission and create payout record.
     *
     * @throws Exception
     */
    public function processSessionCompletion(TherapySession $session): TherapistPayout
    {
        return DB::transaction(function () use ($session) {
            $commission = $this->calculateCommission($session);

            // Update the session with commission details
            $keepPercent = 0;
            if ($session->payment_method === 'subscription') {
                $keepPercent = $this->therapistKeepPercent(self::SUBSCRIPTION_SESSION_BASE_VALUE, $session->therapist?->therapist ?? null);
            } else {
                $keepPercent = $this->therapistKeepPercent((float) $session->session_rate, $session->therapist?->therapist ?? null);
            }
            $session->update([
                'commission_amount' => $commission,
                'commission_percentage' => $keepPercent,
                'is_paid_out' => false,
            ]);

            // Get the therapist's profile ID
            $therapistUser = $session->therapist;
            if (! $therapistUser || ! $therapistUser->therapistProfile) {
                throw new Exception("Therapist profile not found for user ID: {$session->therapist_id}");
            }

            // Create the pending payout record
            return TherapistPayout::create([
                'uuid' => (string) Str::uuid(),
                'therapist_id' => $therapistUser->therapistProfile->id,
                'amount' => $commission,
                'currency' => 'NGN',
                'payment_reason' => 'Session Commission',
                'status' => 'pending',
                'initiated_at' => now(),
                'metadata' => [
                    'session_id' => $session->id,
                    'session_uuid' => $session->uuid,
                    'payment_method' => $session->payment_method,
                    'commission_percentage' => $keepPercent,
                ],
            ]);
        });
    }

    /**
     * Process the completion of a group session.
     */
    public function processGroupSessionCompletion(GroupSession $session): TherapistPayout
    {
        return DB::transaction(function () use ($session) {
            $commission = $this->calculateGroupCommission($session);
            $therapistUser = $session->therapist;
            $therapistModel = $therapistUser?->therapistProfile ?? null;

            if (! $therapistUser || ! $therapistModel) {
                throw new Exception("Therapist profile not found for group session: {$session->uuid}");
            }

            // For metadata
            $perSeatPrice = $session->price_per_seat_kobo / 100.0;
            $keepPercent = $this->therapistKeepPercent($perSeatPrice, $therapistModel);

            $session->update([
                'commission_amount' => $commission,
                'commission_percentage' => $keepPercent,
                'status' => 'completed',
            ]);

            return TherapistPayout::create([
                'uuid' => (string) Str::uuid(),
                'therapist_id' => $therapistModel->id,
                'amount' => $commission,
                'currency' => 'NGN',
                'payment_reason' => 'Group Session Commission',
                'status' => 'pending',
                'initiated_at' => now(),
                'metadata' => [
                    'group_session_id' => $session->id,
                    'group_session_uuid' => $session->uuid,
                    'session_type' => $session->session_type,
                    'commission_percentage' => $keepPercent,
                ],
            ]);
        });
    }
}
