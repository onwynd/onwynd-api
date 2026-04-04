<?php

namespace App\Http\Controllers\API\V1\Payment;

use App\Http\Controllers\Controller;
use App\Services\PaymentService\PaystackService;
use Illuminate\Http\Request;

class PaystackController extends Controller
{
    public function initialize(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'email' => 'nullable|email',
        ]);

        $service = new PaystackService;
        $email = $request->user()?->email ?? $request->get('email');
        $resp = $service->initializeTransaction(
            (int) $request->get('amount'),
            $email,
            ['source' => 'onwynd_api', 'plan_uuid' => $request->get('plan_uuid')]
        );

        // Normalize to standard API format
        if (($resp['status'] ?? false) && isset($resp['data'])) {
            return response()->json([
                'success' => true,
                'message' => $resp['message'] ?? 'Payment initialized',
                'data' => [
                    'authorization_url' => $resp['data']['authorization_url'] ?? null,
                    'access_code' => $resp['data']['access_code'] ?? null,
                    'reference' => $resp['data']['reference'] ?? null,
                    'gateway' => 'paystack',
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $resp['message'] ?? 'Payment initialization failed',
        ], 400);
    }
}
