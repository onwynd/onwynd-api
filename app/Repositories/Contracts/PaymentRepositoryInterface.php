<?php

namespace App\Repositories\Contracts;

interface PaymentRepositoryInterface
{
    public function createPaymentIntent($amount, $currency = 'usd');

    public function processPayment($paymentId);

    public function getUserPayments($userId);

    public function getSubscriptionStatus($userId);
}
