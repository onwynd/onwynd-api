<?php

namespace App\Exports;

use App\Models\TherapySession;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * SessionsExport
 *
 * Export class for therapy sessions
 */
class SessionsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @var int|null
     */
    private $therapistId;

    /**
     * Constructor
     */
    public function __construct($therapistId = null)
    {
        $this->therapistId = $therapistId;
    }

    /**
     * Query
     */
    public function query()
    {
        $query = TherapySession::query()->with('user', 'therapist');

        if ($this->therapistId) {
            $query->where('therapist_id', $this->therapistId);
        }

        return $query->where('status', 'completed');
    }

    /**
     * Headings
     */
    public function headings(): array
    {
        return [
            'Session ID',
            'Patient Name',
            'Therapist Name',
            'Session Type',
            'Scheduled Date',
            'Scheduled Time',
            'Duration (mins)',
            'Session Fee (₦)',
            'Status',
            'Payment Status',
            'Rating',
            'Completed Date',
        ];
    }

    /**
     * Map
     */
    public function map($session): array
    {
        $rating = $session->therapist->ratings()
            ->where('user_id', $session->user_id)
            ->where('session_id', $session->id)
            ->first();

        return [
            $session->id,
            $session->user->full_name,
            $session->therapist->full_name,
            $session->session_type,
            $session->scheduled_date,
            $session->scheduled_time,
            $session->duration_minutes,
            number_format($session->session_fee, 2),
            $session->status,
            $session->payment_status,
            $rating ? $rating->rating.' ⭐' : 'No rating',
            $session->completed_at?->format('Y-m-d H:i'),
        ];
    }
}
