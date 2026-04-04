<?php

namespace App\Console\Commands;

use App\Models\Payment\Subscription;
use App\Services\NotificationService\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessExpiredSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscriptions:process-expired 
                            {--days=3 : Days before expiry to send warning}
                            {--auto-renew : Automatically renew subscriptions if payment method exists}';

    /**
     * The console command description.
     */
    protected $description = 'Process expired subscriptions, send warnings, and handle auto-renewal';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $notificationService = app(NotificationService::class);
        // $subscriptionService = app(SubscriptionService::class);  // Not implemented yet
        $daysBeforeExpiry = (int) $this->option('days');
        $autoRenew = $this->option('auto-renew');

        $this->info('🔄 Processing expired subscriptions...');
        $this->newLine();

        try {
            // 1. Find subscriptions expiring soon
            $expiringSubscriptions = Subscription::where('status', 'active')
                ->whereDate('expires_at', '<=', now()->addDays($daysBeforeExpiry))
                ->whereDate('expires_at', '>', now())
                ->get();

            if ($expiringSubscriptions->count() > 0) {
                $this->info("📢 Found {$expiringSubscriptions->count()} subscriptions expiring soon");
                $bar = $this->output->createProgressBar($expiringSubscriptions->count());

                foreach ($expiringSubscriptions as $subscription) {
                    // Send expiry warning notification
                    $notificationService->sendSubscriptionExpiryWarning($subscription);

                    $this->line("⚠️  Warning sent to user {$subscription->user_id} - expires on {$subscription->expires_at->format('Y-m-d')}");
                    $bar->advance();
                }

                $bar->finish();
                $this->newLine();
            }

            // 2. Process actually expired subscriptions
            $expiredSubscriptions = Subscription::where('status', 'active')
                ->whereDate('expires_at', '<', now())
                ->get();

            if ($expiredSubscriptions->count() > 0) {
                $this->info("❌ Found {$expiredSubscriptions->count()} expired subscriptions");
                $bar = $this->output->createProgressBar($expiredSubscriptions->count());

                foreach ($expiredSubscriptions as $subscription) {
                    if ($autoRenew && $subscription->user->paymentMethods()->exists()) {
                        // Attempt auto-renewal
                        try {
                            // $subscriptionService->renew($subscription);  // Not implemented yet
                            $this->line("✅ Auto-renewal would be triggered for user {$subscription->user_id}");
                            $notificationService->sendSubscriptionRenewalConfirmation($subscription);
                        } catch (\Exception $e) {
                            // Renewal failed, mark as expired
                            $subscription->update(['status' => 'expired']);
                            $notificationService->sendSubscriptionExpiredNotification($subscription);
                            $this->line("❌ Auto-renewal failed for user {$subscription->user_id}: {$e->getMessage()}");
                        }
                    } else {
                        // No auto-renewal, mark as expired
                        $subscription->update(['status' => 'expired']);
                        $notificationService->sendSubscriptionExpiredNotification($subscription);
                        $this->line("⏱️  Subscription marked as expired for user {$subscription->user_id}");
                    }

                    $bar->advance();
                }

                $bar->finish();
                $this->newLine();
            }

            // 3. Handle paused subscriptions
            $pausedSubscriptions = Subscription::where('status', 'paused')
                ->where('pause_until', '<=', now())
                ->get();

            if ($pausedSubscriptions->count() > 0) {
                $this->info("▶️  Found {$pausedSubscriptions->count()} paused subscriptions to resume");
                $bar = $this->output->createProgressBar($pausedSubscriptions->count());

                foreach ($pausedSubscriptions as $subscription) {
                    $subscription->update([
                        'status' => 'active',
                        'pause_until' => null,
                    ]);
                    $notificationService->sendSubscriptionResumedNotification($subscription);
                    $this->line("▶️  Subscription resumed for user {$subscription->user_id}");
                    $bar->advance();
                }

                $bar->finish();
                $this->newLine();
            }

            // 4. Generate summary report
            $stats = [
                'expiring_soon' => $expiringSubscriptions->count(),
                'expired' => $expiredSubscriptions->count(),
                'resumed' => $pausedSubscriptions->count(),
                'auto_renewed' => $expiredSubscriptions->where('status', 'active')->count(),
            ];

            $this->info('📊 Summary Report:');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Expiring Soon (warning sent)', $stats['expiring_soon']],
                    ['Expired (processed)', $stats['expired']],
                    ['Resumed from pause', $stats['resumed']],
                    ['Auto-renewed', $stats['auto_renewed']],
                ]
            );

            Log::info('Subscription expiry processing completed', $stats);
            $this->info('✅ Subscription processing completed successfully');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error processing subscriptions: {$e->getMessage()}");
            Log::error('Subscription processing failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return self::FAILURE;
        }
    }
}
