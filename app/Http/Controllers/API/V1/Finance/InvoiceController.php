<?php

namespace App\Http\Controllers\API\V1\Finance;

use App\Http\Controllers\API\BaseController;
use App\Mail\InvoiceEmail;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InvoiceController extends BaseController
{
    public function index(Request $request)
    {
        $query = Invoice::with('user:id,first_name,last_name,email');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate(20);

        // Flatten user info into top-level fields for the frontend
        $invoices->getCollection()->transform(function ($inv) {
            $inv->user_name  = $inv->user ? trim("{$inv->user->first_name} {$inv->user->last_name}") : null;
            $inv->user_email = $inv->user?->email;
            return $inv;
        });

        return $this->sendResponse($invoices, 'Invoices retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id'    => 'required_without:user_email|exists:users,id',
            'user_email' => 'required_without:user_id|email|exists:users,email',
            'amount'     => 'required|numeric|min:0',
            'currency'   => 'nullable|string|max:10',
            'due_date'   => 'nullable|date',
            'status'     => 'nullable|in:pending,paid,overdue,cancelled',
            'items'      => 'nullable|array',
            'notes'      => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // Resolve user
        if ($request->filled('user_id')) {
            $user = User::find($request->user_id);
        } else {
            $user = User::where('email', $request->user_email)->first();
        }

        if (! $user) {
            return $this->sendError('User not found.', 422);
        }

        $invoice = Invoice::create([
            'invoice_number' => 'INV-'.strtoupper(Str::random(8)),
            'user_id'        => $user->id,
            'amount'         => $request->amount,
            'currency'       => $request->currency ?? 'NGN',
            'status'         => $request->status ?? 'pending',
            'due_date'       => $request->due_date,
            'items'          => $request->items,
            'notes'          => $request->notes,
        ]);

        // If created as already paid, send receipt email immediately
        if ($invoice->status === 'paid') {
            $this->sendInvoiceEmail($invoice, $user);
            $invoice->paid_at = now();
            $invoice->save();
        }

        return $this->sendResponse($invoice->load('user:id,first_name,last_name,email'), 'Invoice created successfully.');
    }

    public function show($id)
    {
        $invoice = Invoice::with('user')->find($id);

        if (! $invoice) {
            return $this->sendError('Invoice not found.');
        }

        return $this->sendResponse($invoice, 'Invoice details retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $invoice = Invoice::with('user')->find($id);

        if (! $invoice) {
            return $this->sendError('Invoice not found.');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,paid,overdue,cancelled',
            'notes'  => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $wasPaid = $invoice->status === 'paid';

        if ($request->has('status')) {
            $invoice->status = $request->status;
            if ($request->status === 'paid' && ! $invoice->paid_at) {
                $invoice->paid_at = now();
            }
        }

        if ($request->has('notes')) {
            $invoice->notes = $request->notes;
        }

        $invoice->save();

        // Send payment receipt email on first transition to 'paid'
        if (! $wasPaid && $invoice->status === 'paid' && $invoice->user) {
            $this->sendInvoiceEmail($invoice, $invoice->user);
        }

        return $this->sendResponse($invoice, 'Invoice updated successfully.');
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function sendInvoiceEmail(Invoice $invoice, User $user): void
    {
        try {
            $items = $invoice->items ?? [
                ['description' => 'Subscription / Service', 'amount' => $invoice->amount],
            ];

            $mailable = new InvoiceEmail(
                name:      trim("{$user->first_name} {$user->last_name}"),
                items:     $items,
                tax:       0,
                total:     $invoice->amount,
                statusUrl: null,
            );

            Mail::to($user->email)->queue($mailable);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Invoice email failed: '.$e->getMessage());
        }
    }
}
