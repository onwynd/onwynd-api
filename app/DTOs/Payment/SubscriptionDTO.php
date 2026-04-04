<?php

namespace App\DTOs\Payment;

class SubscriptionDTO
{
    public function __construct(
        public readonly string $userId,
        public readonly string $planId,
        public readonly string $paymentMethodId,
        public readonly string $interval = 'monthly'
    ) {}
}
