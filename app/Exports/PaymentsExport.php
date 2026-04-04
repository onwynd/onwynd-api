<?php

namespace App\Exports;

use App\Models\Payment;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaymentsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @var string|null
     */
    private $status;

    /**
     * @var string|null
     */
    private $startDate;

    /**
     * @var string|null
     */
    private $endDate;

    /**
     * Constructor
     */
    public function __construct($status = null, $startDate = null, $endDate = null)
    {
        $this->status = $status;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * Query
     */
    public function query()
    {
        $query = Payment::query()->with('user', 'session');

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->startDate && $this->endDate) {
            $query->whereBetween('created_at', [$this->startDate, $this->endDate]);
        }

        return $query->orderByDesc('created_at');
    }

    /**
     * Headings
     */
    public function headings(): array
    {
        return [
            'Payment ID',
            'User Name',
            'User Email',
            'Amount (₦)',
            'Currency',
            'Payment Type',
            'Status',
            'Payment Gateway',
            'Reference',
            'Session ID',
            'Created Date',
        ];
    }

    /**
     * Map
     */
    public function map($payment): array
    {
        return [
            $payment->id,
            $payment->user->full_name,
            $payment->user->email,
            number_format($payment->amount, 2),
            $payment->currency,
            $payment->payment_type,
            $payment->status,
            $payment->payment_gateway ?? 'N/A',
            $payment->payment_reference ?? 'N/A',
            $payment->session_id ?? 'N/A',
            $payment->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
