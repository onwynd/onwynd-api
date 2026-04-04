<?php

namespace App\Services;

use App\Models\Institutional\OrganizationMember;
use App\Models\User;
use App\Services\PlatformSettingsService;

class BookingFeeService
{
    /**
     * Calculate the booking fee for a given user and session context.
     *
     * Returns array with keys:
     *   fee              float  — amount to charge (0 if waived)
     *   waived           bool   — whether the fee is waived
     *   waiver_reason    string|null — 'fee_disabled'|'premium_benefit'|'corporate_member'|'free_monthly_consult'|null
     *   fee_enabled      bool   — whether the feature is enabled globally
     *   base_fee         float  — the configured fee amount (present when fee_enabled = true)
     *   sessions_this_month  int|null
     *   free_consults_remaining int|null
     */
    public function calculate(User $user, string $currency = 'NGN'): array
    {
        $feeEnabled = filter_var(
            PlatformSettingsService::get('booking_fee_enabled', 'true'),
            FILTER_VALIDATE_BOOLEAN
        );

        if (!$feeEnabled) {
            return [
                'fee'          => 0.0,
                'waived'       => true,
                'waiver_reason' => 'fee_disabled',
                'fee_enabled'  => false,
            ];
        }

        $baseFee = $currency === 'USD'
            ? (float) PlatformSettingsService::get('booking_fee_usd', '0.10')
            : (float) PlatformSettingsService::get('booking_fee_ngn', '100');

        // Premium (paid) users: waived
        if ($this->userIsPremium($user)) {
            return [
                'fee'           => 0.0,
                'waived'        => true,
                'waiver_reason' => 'premium_benefit',
                'fee_enabled'   => true,
                'base_fee'      => $baseFee,
            ];
        }

        // Corporate members: waived
        if ($this->userIsCorporate($user)) {
            return [
                'fee'           => 0.0,
                'waived'        => true,
                'waiver_reason' => 'corporate_member',
                'fee_enabled'   => true,
                'base_fee'      => $baseFee,
            ];
        }

        // Freemium users always pay the booking fee
        return [
            'fee'           => $baseFee,
            'waived'        => false,
            'waiver_reason' => null,
            'fee_enabled'   => true,
            'base_fee'      => $baseFee,
        ];
    }

    private function userIsPremium(User $user): bool
    {
        // Only paid plans qualify — exclude the free 'basic' plan
        return $user->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->whereHas('plan', fn ($q) => $q->where('price', '>', 0)->where('slug', '!=', 'basic'))
            ->exists();
    }

    private function userIsCorporate(User $user): bool
    {
        return OrganizationMember::where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }


}
