<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService\PaymentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentManagementController extends Controller
{
    public function __construct(private PaymentProcessor $processor) {}

    /**
     * GET /api/v1/admin/payments
     * List all payments with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with('user:id,first_name,last_name,email')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('gateway')) {
            $query->where('payment_gateway', $request->gateway);
        }
        if ($request->filled('search')) {
            $q = $request->search;
            $query->whereHas('user', function ($sq) use ($q) {
                $sq->where('email', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%");
            })->orWhere('payment_reference', 'like', "%{$q}%");
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $payments = $query->paginate(min($request->input('per_page', 25), 100));

        return response()->json(['status' => 'success', 'data' => $payments]);
    }

    /**
     * GET /api/v1/admin/payments/refunds
     * List all refunded / partially refunded payments.
     */
    public function refunds(Request $request): JsonResponse
    {
        $query = Payment::with('user:id,first_name,last_name,email')
            ->whereIn('status', ['refunded', 'partially_refunded'])
            ->latest('refunded_at');

        if ($request->filled('gateway')) {
            $query->where('payment_gateway', $request->gateway);
        }
        if ($request->filled('from')) {
            $query->whereDate('refunded_at', '>=', $request->from);
        }

        $payments = $query->paginate(min($request->input('per_page', 25), 100));

        return response()->json(['status' => 'success', 'data' => $payments]);
    }

    /**
     * POST /api/v1/admin/payments/{payment}/refund
     * Admin-initiated full or partial refund.
     */
    public function refund(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'nullable|numeric|min:0.01|max:'.$payment->amount,
            'reason' => 'nullable|string|max:500',
        ]);

        if (! in_array($payment->status, ['completed', 'paid', 'partially_refunded'])) {
            return response()->json(['status' => 'error', 'message' => 'Payment cannot be refunded in its current state.'], 422);
        }

        try {
            $result = $this->processor->refundPayment($payment, [
                'amount' => $data['amount'] ?? null,
                'reason' => $data['reason'] ?? 'admin_initiated',
            ]);

            Log::info('Admin refund issued', [
                'payment_id' => $payment->id,
                'admin_id' => Auth::id(),
                'amount' => $data['amount'] ?? 'full',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Refund processed successfully.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Admin refund failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);

            return response()->json(['status' => 'error', 'message' => 'Refund failed: '.$e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/admin/payments/disputes
     * List payments flagged as disputed.
     */
    public function disputes(Request $request): JsonResponse
    {
        $query = Payment::with('user:id,first_name,last_name,email')
            ->where('status', 'disputed')
            ->latest();

        $payments = $query->paginate(min($request->input('per_page', 25), 100));

        return response()->json(['status' => 'success', 'data' => $payments]);
    }

    /**
     * PATCH /api/v1/admin/payments/{payment}/dispute
     * Flag a payment as disputed or resolve a dispute.
     */
    public function updateDispute(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|in:flag,resolve,accept',
            'notes' => 'nullable|string|max:1000',
        ]);

        $statusMap = [
            'flag' => 'disputed',
            'resolve' => 'completed',
            'accept' => 'refunded',
        ];

        $newStatus = $statusMap[$data['action']];
        $payment->update(['status' => $newStatus]);

        // If accepting dispute (issuing refund)
        if ($data['action'] === 'accept') {
            try {
                $this->processor->refundPayment($payment, [
                    'reason' => 'dispute_accepted: '.($data['notes'] ?? ''),
                ]);
            } catch (\Exception $e) {
                Log::error('Dispute refund failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            }
        }

        Log::info('Dispute action', [
            'payment_id' => $payment->id,
            'action' => $data['action'],
            'admin_id' => Auth::id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Dispute updated.',
            'data' => $payment->fresh(),
        ]);
    }
}
