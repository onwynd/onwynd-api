<?php

namespace App\Listeners;

use App\Events\PaymentProcessed;
use App\Services\NotificationService\NotificationService;
use Illuminate\Support\Facades\Log;

class SendPaymentConfirmation
{
    /**
     * @var NotificationService
     */
    private $notificationService;

    /**
     * Create the event listener
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event
     */
    public function handle(PaymentProcessed $event): void
    {
        try {
            $payment = $event->payment;

            Log::info('Sending payment confirmation', ['payment_id' => $payment->id]);

            // Update session payment status
            if ($payment->session) {
                $payment->session->update(['payment_status' => 'completed']);
            }

            // Send confirmation email
            $this->notificationService->sendPaymentConfirmation($payment->user, $payment);

        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
