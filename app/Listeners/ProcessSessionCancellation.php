<?php

namespace App\Listeners;

use App\Events\SessionCancelled;
use App\Services\NotificationService\NotificationService;
use App\Services\PaymentService\PaymentProcessor;
use Illuminate\Support\Facades\Log;

class ProcessSessionCancellation
{
    /**
     * @var PaymentProcessor
     */
    private $paymentProcessor;

    /**
     * @var NotificationService
     */
    private $notificationService;

    /**
     * Create the event listener
     */
    public function __construct(PaymentProcessor $paymentProcessor, NotificationService $notificationService)
    {
        $this->paymentProcessor = $paymentProcessor;
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event
     */
    public function handle(SessionCancelled $event): void
    {
        try {
            $session = $event->session;

            Log::info('Processing session cancellation', ['session_id' => $session->id]);

            // Process refund if payment was completed
            if ($session->payment && $session->payment->status === 'completed') {
                $this->paymentProcessor->refundPayment($session->payment);
            }

            // Send cancellation notification
            $this->notificationService->sendSessionCancellationNotification($session->user, $session->therapist);

        } catch (\Exception $e) {
            Log::error('Failed to process session cancellation', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
