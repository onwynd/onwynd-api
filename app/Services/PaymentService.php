<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Initialize a Paystack transaction
     */
    public function initializePaystack($email, $amount, $reference = null)
    {
        try {
            $response = Http::withToken(config('services.paystack.secretKey'))
                ->post(config('services.paystack.paymentUrl').'/transaction/initialize', [
                    'email' => $email,
                    'amount' => $amount * 100, // Amount in kobo
                    'reference' => $reference,
                    'callback_url' => route('payment.callback', ['gateway' => 'paystack']),
                ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Paystack Init Error: '.$e->getMessage());

            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Verify a Paystack transaction
     */
    public function verifyPaystack($reference)
    {
        try {
            $response = Http::withToken(config('services.paystack.secretKey'))
                ->get(config('services.paystack.paymentUrl')."/transaction/verify/{$reference}");

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Paystack Verify Error: '.$e->getMessage());

            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Initialize a Flutterwave transaction
     */
    public function initializeFlutterwave($user, $amount, $reference)
    {
        try {
            $response = Http::withToken(config('services.flutterwave.secretKey'))
                ->post('https://api.flutterwave.com/v3/payments', [
                    'tx_ref' => $reference,
                    'amount' => $amount,
                    'currency' => 'NGN',
                    'redirect_url' => route('payment.callback', ['gateway' => 'flutterwave']),
                    'customer' => [
                        'email' => $user->email,
                        'phonenumber' => $user->phone,
                        'name' => $user->first_name.' '.$user->last_name,
                    ],
                    'customizations' => [
                        'title' => 'Onwynd Therapy Session',
                        'logo' => asset('logo.png'),
                    ],
                ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Flutterwave Init Error: '.$e->getMessage());

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Verify a Flutterwave transaction
     */
    public function verifyFlutterwave($transactionId)
    {
        try {
            $response = Http::withToken(config('services.flutterwave.secretKey'))
                ->get("https://api.flutterwave.com/v3/transactions/{$transactionId}/verify");

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Flutterwave Verify Error: '.$e->getMessage());

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
