<?php

namespace App\Observers;

use App\Events\PaymentFailed;
use App\Events\PaymentProcessed;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class PaymentObserver
{
    /**
     * Handle the Payment "created" event
     */
    public function created(Payment $payment): void
    {
        Log::info('Payment created', ['payment_id' => $payment->id, 'amount' => $payment->amount]);
    }

    /**
     * Handle the Payment "updated" event
     */
    public function updated(Payment $payment): void
    {
        Log::info('Payment updated', ['payment_id' => $payment->id, 'status' => $payment->status]);

        // Check if payment was completed
        if ($payment->isDirty('status') && $payment->status === 'completed') {
            event(new PaymentProcessed($payment));
        }

        // Check if payment failed
        if ($payment->isDirty('status') && $payment->status === 'failed') {
            event(new PaymentFailed($payment));
        }
    }

    /**
     * Handle the Payment "deleted" event
     */
    public function deleted(Payment $payment): void
    {
        Log::warning('Payment deleted', ['payment_id' => $payment->id]);
    }
}
