<?php

namespace App\Services;

use App\Models\ReferralRewardConfig;
use App\Models\User;
use App\Models\UserReferral;
use App\Models\UserReferralCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserReferralService
{
    /**
     * Get or create a referral code for a user.
     * Tier is determined by whether the user has an active paid subscription.
     * Only called when the user explicitly visits their referral page.
     */
    public function getOrCreateCode(User $user): UserReferralCode
    {
        $existing = UserReferralCode::where('user_id', $user->id)->where('is_active', true)->first();
        if ($existing) {
            return $existing;
        }

        $tier = $this->resolveUserTier($user);

        return UserReferralCode::create([
            'user_id'    => $user->id,
            'tier'       => $tier,
            'uses_count' => 0,
            'is_active'  => true,
        ]);
    }

    /**
     * Process a user referral code at sign-up.
     * Called from FirebaseAuthController when a new user provides a referral code
     * that belongs to the user_referral_codes table (not ambassador).
     *
     * Returns true if processed successfully, false if code is invalid/inactive.
     */
    public function processSignupReferral(User $newUser, string $rawCode): bool
    {
        $code = UserReferralCode::where('code', strtoupper($rawCode))
            ->where('is_active', true)
            ->first();

        if (! $code) {
            return false;
        }

        // Prevent self-referral
        if ($code->user_id === $newUser->id) {
            return false;
        }

        // Prevent duplicate referral records for this user
        if (UserReferral::where('referred_user_id', $newUser->id)->exists()) {
            return false;
        }

        DB::transaction(function () use ($code, $newUser) {
            $referral = UserReferral::create([
                'referrer_user_id' => $code->user_id,
                'referred_user_id' => $newUser->id,
                'referral_code_id' => $code->id,
                'referrer_tier'    => $code->tier,
                'status'           => 'pending',
            ]);

            $code->increment('uses_count');

            // 1. Always reward the referee (+10 AI chats on signup)
            $this->applyRefereeReward($newUser, $referral);

            // 2. Reward the referrer based on their tier
            if ($code->tier === 'freemium') {
                // Freemium referrer: +5 AI chats immediately
                $this->applyFreemiumReferrerReward($code->user, $referral);
            }
            // Paid referrer reward is deferred until the referee makes their first subscription payment
        });

        return true;
    }

    /**
     * Called when a user makes their first subscription payment.
     * Rewards any paid-tier referrer whose code this user signed up with.
     */
    public function processPaidReferrerReward(User $paidUser): void
    {
        $referral = UserReferral::where('referred_user_id', $paidUser->id)
            ->whereIn('status', ['pending', 'referee_rewarded'])
            ->where('referrer_tier', 'paid')
            ->first();

        if (! $referral) {
            return;
        }

        $config = ReferralRewardConfig::forTier('paid');
        if (! $config) {
            return;
        }

        DB::transaction(function () use ($referral, $config) {
            $referrer = $referral->referrer;

            // Add the % discount, capped at max_discount_cap
            $currentDiscount = (float) $referrer->pending_referral_discount;
            $cap             = $config->max_discount_cap ? (float) $config->max_discount_cap : 100;
            $earned          = (float) $config->reward_value;
            $newDiscount     = min($currentDiscount + $earned, $cap);

            $referrer->increment('pending_referral_discount', $newDiscount - $currentDiscount);

            $newStatus = $referral->status === 'referee_rewarded' ? 'fully_rewarded' : 'referrer_rewarded';
            $referral->update([
                'status'               => $newStatus,
                'referrer_rewarded_at' => now(),
            ]);

            Log::info('UserReferralService: paid referrer reward applied', [
                'referrer_id'     => $referrer->id,
                'discount_earned' => $newDiscount - $currentDiscount,
                'total_discount'  => $newDiscount,
            ]);
        });
    }

    /**
     * Consume and return the pending referral discount for a user (called at renewal).
     * Returns the % discount to apply and resets the balance to 0.
     */
    public function consumeReferralDiscount(User $user): float
    {
        $discount = (float) $user->pending_referral_discount;
        if ($discount > 0) {
            $user->update(['pending_referral_discount' => 0]);
        }

        return $discount;
    }

    // -------------------------------------------------------------------------

    private function applyRefereeReward(User $newUser, UserReferral $referral): void
    {
        $config = ReferralRewardConfig::forTier('referee');
        if (! $config || $config->reward_type !== 'ai_quota') {
            return;
        }

        $newUser->increment('referral_ai_bonus', (int) $config->reward_value);

        $status = $referral->status === 'referrer_rewarded' ? 'fully_rewarded' : 'referee_rewarded';
        $referral->update([
            'status'              => $status,
            'referee_rewarded_at' => now(),
        ]);
    }

    private function applyFreemiumReferrerReward(User $referrer, UserReferral $referral): void
    {
        $config = ReferralRewardConfig::forTier('freemium');
        if (! $config || $config->reward_type !== 'ai_quota') {
            return;
        }

        $referrer->increment('referral_ai_bonus', (int) $config->reward_value);

        $status = $referral->status === 'referee_rewarded' ? 'fully_rewarded' : 'referrer_rewarded';
        $referral->update([
            'status'               => $status,
            'referrer_rewarded_at' => now(),
        ]);
    }

    private function resolveUserTier(User $user): string
    {
        // A user is 'paid' if they have an active subscription
        $hasPaidSub = \App\Models\Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        return $hasPaidSub ? 'paid' : 'freemium';
    }
}
