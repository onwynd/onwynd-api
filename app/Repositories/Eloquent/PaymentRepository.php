<?php

namespace App\Repositories\Eloquent;

use App\Models\Payment;
use App\Models\Subscription;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createPaymentIntent($amount, $currency = 'usd')
    {
        return PaymentIntent::create([
            'amount' => $amount * 100, // Amount in cents
            'currency' => $currency,
        ]);
    }

    public function processPayment($paymentId)
    {
        $payment = Payment::find($paymentId);
        if ($payment) {
            $payment->update(['status' => 'completed']);

            return $payment;
        }

        return null;
    }

    public function getUserPayments($userId)
    {
        return Payment::where('user_id', $userId)->orderBy('created_at', 'desc')->get();
    }

    public function getSubscriptionStatus($userId)
    {
        return Subscription::where('user_id', $userId)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->first();
    }
}
