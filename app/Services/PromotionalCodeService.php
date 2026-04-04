<?php

namespace App\Services;

use App\Models\PromotionalCode;
use App\Models\PromotionalCodeUsage;
use Illuminate\Support\Facades\DB;

class PromotionalCodeService
{
    /**
     * Validate a promotional code for the given context.
     *
     * @return array{valid: bool, message: string, code: PromotionalCode|null, discount_amount: float}
     */
    public function validate(
        string $code,
        int $userId,
        string $currency,
        float $sessionFee,
        string $appliesTo = 'session'
    ): array {
        $promoCode = PromotionalCode::where('code', strtoupper($code))->first();

        if (! $promoCode) {
            return [
                'valid'           => false,
                'message'         => 'Promotional code not found.',
                'code'            => null,
                'discount_amount' => 0.0,
            ];
        }

        if (! $promoCode->is_active) {
            return [
                'valid'           => false,
                'message'         => 'This code is not currently active.',
                'code'            => null,
                'discount_amount' => 0.0,
            ];
        }

        if ($promoCode->valid_from !== null && now()->lt($promoCode->valid_from)) {
            return [
                'valid'           => false,
                'message'         => 'This code is not yet valid.',
                'code'            => null,
                'discount_amount' => 0.0,
            ];
        }

        if ($promoCode->valid_until !== null && now()->gt($promoCode->valid_until)) {
            return [
                'valid'           => false,
                'message'         => 'This code has expired.',
                'code'            => null,
                'discount_amount' => 0.0,
            ];
        }

        if ($promoCode->max_uses !== null && $promoCode->uses_count >= $promoCode->max_uses) {
            return [
                'valid'           => false,
                'message'         => 'This code has reached its maximum number of uses.',
                'code'            => null,
                'discount_amount' => 0.0,
            ];
        }

        if ($promoCode->applies_to !== 'all' && $promoCode->applies_to !== $appliesTo) {
            return [
                'valid'           => false,
                'message'         => "This code cannot be used for {$appliesTo}.",
                'code'            => null,
                'discount_amount' => 0.0,
            ];
        }

        if ($promoCode->currency !== null && $promoCode->currency !== strtoupper($currency)) {
            return [
                'valid'           => false,
                'message'         => 'This code is not valid for your currency.',
                'code'            => null,
                'discount_amount' => 0.0,
            ];
        }

        if ($promoCode->max_uses_per_user !== null) {
            $userUsageCount = PromotionalCodeUsage::where('promotional_code_id', $promoCode->id)
                ->where('user_id', $userId)
                ->count();

            if ($userUsageCount >= $promoCode->max_uses_per_user) {
                return [
                    'valid'           => false,
                    'message'         => 'You have already used this code the maximum number of times.',
                    'code'            => null,
                    'discount_amount' => 0.0,
                ];
            }
        }

        $discountAmount = $this->calculateDiscount($promoCode, $sessionFee);

        return [
            'valid'           => true,
            'message'         => 'Promotional code applied successfully.',
            'code'            => $promoCode,
            'discount_amount' => $discountAmount,
        ];
    }

    /**
     * Redeem a promotional code, recording usage and incrementing the counter.
     */
    public function redeem(
        PromotionalCode $promoCode,
        int $userId,
        float $discountApplied,
        ?int $sessionId = null
    ): PromotionalCodeUsage {
        return DB::transaction(function () use ($promoCode, $userId, $discountApplied, $sessionId) {
            $usage = PromotionalCodeUsage::create([
                'promotional_code_id' => $promoCode->id,
                'user_id'             => $userId,
                'session_id'          => $sessionId,
                'discount_applied'    => $discountApplied,
            ]);

            $promoCode->increment('uses_count');
            $promoCode->refresh();

            if ($promoCode->max_uses !== null && $promoCode->uses_count >= $promoCode->max_uses) {
                $promoCode->update(['is_active' => false]);
            }

            return $usage;
        });
    }

    private function calculateDiscount(PromotionalCode $promoCode, float $sessionFee): float
    {
        if ($promoCode->type === 'percentage') {
            return round($sessionFee * ((float) $promoCode->discount_value / 100), 2);
        }

        // fixed — never discount more than the session fee
        return min((float) $promoCode->discount_value, $sessionFee);
    }
}
