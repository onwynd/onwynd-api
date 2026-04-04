<?php

namespace App\Jobs;

use App\Models\Payment\Subscription as PaymentSubscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DowngradeExpiredGraceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $userId) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        // If the user renewed during the grace period, their status will no longer be
        // 'past_due' — bail out so we don't undo a valid subscription.
        if ($user->subscription_status !== 'past_due') {
            Log::info('DowngradeExpiredGraceJob: user renewed during grace period, skipping downgrade', [
                'user_id' => $this->userId,
                'status' => $user->subscription_status,
            ]);

            return;
        }

        $user->subscription_status = 'free';
        $user->subscription_ends_at = now();
        $user->save();

        PaymentSubscription::where('user_id', $user->id)
            ->where('status', 'past_due')
            ->update(['status' => 'expired', 'expires_at' => now(), 'canceled_at' => now()]);

        Log::info('DowngradeExpiredGraceJob: user downgraded after 3-day grace period', [
            'user_id' => $user->id,
        ]);
    }
}
