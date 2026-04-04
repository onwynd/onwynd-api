<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends BaseController
{
    public function index(Request $request)
    {
        $payments = Payment::where('user_id', $request->user()->id)
            ->with(['session', 'subscription'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return $this->sendResponse($payments, 'Payment history retrieved successfully.');
    }

    public function show($id)
    {
        $payment = Payment::with(['session', 'subscription'])
            ->where('user_id', auth()->id())
            ->find($id);

        if (! $payment) {
            return $this->sendError('Payment not found.');
        }

        return $this->sendResponse($payment, 'Payment details retrieved successfully.');
    }
}
