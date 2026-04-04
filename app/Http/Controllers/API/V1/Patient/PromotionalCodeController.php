<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Services\PromotionalCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PromotionalCodeController extends BaseController
{
    public function __construct(private readonly PromotionalCodeService $promoService)
    {
    }

    /**
     * Validate a promotional code without redeeming it.
     *
     * POST /api/v1/promo-codes/validate
     *
     * Body: { code, currency, session_fee, therapist_id? }
     */
    public function validateCode(Request $request): JsonResponse
    {
        $request->validate([
            'code'        => 'required|string|max:50',
            'currency'    => 'required|string|in:NGN,USD',
            'session_fee' => 'required|numeric|min:0',
            'therapist_id' => 'nullable|integer',
        ]);

        $result = $this->promoService->validate(
            code:       $request->code,
            userId:     Auth::id(),
            currency:   strtoupper($request->currency),
            sessionFee: (float) $request->session_fee,
            appliesTo:  'session'
        );

        if (! $result['valid']) {
            return $this->sendError($result['message'], [], 422);
        }

        $promoCode = $result['code'];

        return $this->sendResponse([
            'valid'           => true,
            'message'         => $result['message'],
            'discount_amount' => $result['discount_amount'],
            'discount_type'   => $promoCode->type,
            'code_details'    => [
                'code'        => $promoCode->code,
                'type'        => $promoCode->type,
                'description' => $promoCode->description,
            ],
        ], $result['message']);
    }
}
