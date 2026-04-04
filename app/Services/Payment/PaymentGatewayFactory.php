<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\UnsupportedCurrencyException;

class PaymentGatewayFactory
{
    /**
     * Resolve the correct payment gateway for the given currency.
     *
     * Gateways are checked in order of their `supports()` implementation.
     * To add a new gateway, bind it in AppServiceProvider and append it here.
     *
     * @throws UnsupportedCurrencyException when no gateway supports the currency.
     */
    public static function resolve(string $currency): PaymentGatewayInterface
    {
        $gateways = [
            app(PaystackGateway::class),
            app(StripeGateway::class),
            // Future: app(FlutterwaveGateway::class), app(MercadoPagoGateway::class)
        ];

        foreach ($gateways as $gateway) {
            if ($gateway->supports($currency)) {
                return $gateway;
            }
        }

        throw new UnsupportedCurrencyException(
            "No payment gateway configured for currency: {$currency}"
        );
    }
}
