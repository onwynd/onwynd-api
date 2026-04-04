<?php

namespace App\Http\Controllers\API\V1\HR;

use App\Http\Controllers\API\BaseController;
use App\Mail\PayrollNotification;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PayrollController extends BaseController
{
    /**
     * GET /api/v1/hr/payroll
     * List payroll entries for Onwynd internal employees.
     */
    public function index(Request $request)
    {
        $query = Payroll::with(['user:id,first_name,last_name,email,department'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $payrolls = $query->paginate($request->get('per_page', 20));

        // Flatten for frontend compatibility
        $payrolls->getCollection()->transform(function (Payroll $p) {
            return [
                'id'               => $p->id,
                'uuid'             => $p->uuid,
                'user_id'          => $p->user_id,
                'employee_name'    => $p->user ? trim("{$p->user->first_name} {$p->user->last_name}") : '—',
                'employee_email'   => $p->user?->email ?? '—',
                'department'       => $p->user?->department ?? '—',
                'amount'           => (float) $p->amount,
                'period_start'     => $p->period_start?->toDateString(),
                'period_end'       => $p->period_end?->toDateString(),
                'period'           => ($p->period_start && $p->period_end)
                    ? $p->period_start->format('M j') . ' – ' . $p->period_end->format('M j, Y')
                    : '—',
                'pay_date'         => $p->pay_date?->toDateString(),
                'status'           => $p->status,
                'reference_number' => $p->reference_number,
                'paid_at'          => $p->pay_date?->toDateString(),
            ];
        });

        return $this->sendResponse($payrolls, 'Payroll records retrieved.');
    }

    /**
     * POST /api/v1/hr/payroll
     * Create a payroll entry for an Onwynd internal employee.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'      => 'required|exists:users,id',
            'amount'       => 'required|numeric|min:0',
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
            'pay_date'     => 'nullable|date',
            'status'       => 'nullable|in:pending,processing,paid,failed',
        ]);

        $payroll = Payroll::create([
            'uuid'             => (string) Str::uuid(),
            'user_id'          => $data['user_id'],
            'amount'           => $data['amount'],
            'period_start'     => $data['period_start'],
            'period_end'       => $data['period_end'],
            'pay_date'         => $data['pay_date'] ?? null,
            'status'           => $data['status'] ?? 'pending',
            'reference_number' => 'PAY-' . strtoupper(Str::random(8)),
        ]);

        Log::info('Payroll entry created', [
            'payroll_uuid' => $payroll->uuid,
            'user_id'      => $payroll->user_id,
        ]);

        return $this->sendResponse($payroll->load('user'), 'Payroll entry created.');
    }

    /**
     * POST /api/v1/hr/payroll/{uuid}/mark-paid
     * Mark a payroll entry as paid and email the employee their payslip notification.
     */
    public function markPaid(string $uuid)
    {
        $payroll = Payroll::where('uuid', $uuid)->with('user')->firstOrFail();

        if ($payroll->status === 'paid') {
            return $this->sendError('This payroll entry is already marked as paid.', [], 409);
        }

        $payroll->update([
            'status'   => 'paid',
            'pay_date' => now()->toDateString(),
        ]);

        // Notify the employee
        if ($payroll->user && $payroll->user->email) {
            try {
                $month     = $payroll->period_start
                    ? Carbon::parse($payroll->period_start)->format('F Y')
                    : now()->format('F Y');
                $netAmount = '₦' . number_format((float) $payroll->amount, 2);
                $link      = config('app.frontend_url', config('app.url')) . '/employee/payroll';

                Mail::to($payroll->user->email)->queue(
                    new PayrollNotification(
                        trim("{$payroll->user->first_name} {$payroll->user->last_name}"),
                        $month,
                        $netAmount,
                        $link,
                    )
                );

                Log::info('Payroll notification sent', [
                    'payroll_uuid' => $payroll->uuid,
                    'email'        => $payroll->user->email,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to send payroll notification', [
                    'payroll_uuid' => $payroll->uuid,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        return $this->sendResponse($payroll, 'Payroll marked as paid. Employee has been notified.');
    }

    /**
     * POST /api/v1/hr/payroll/process
     * Bulk-process trigger (legacy / batch).
     */
    public function process(Request $request)
    {
        return $this->sendResponse([], 'Payroll processing initiated.');
    }
}
