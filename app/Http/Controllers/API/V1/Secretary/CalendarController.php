<?php

namespace App\Http\Controllers\API\V1\Secretary;

use App\Http\Controllers\API\BaseController;
use App\Models\Appointment;
use App\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends BaseController
{
    /**
     * Get all calendar events (appointments and visitors).
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        // Fetch Appointments
        $appointments = Appointment::whereBetween('start_time', [$startDate, $endDate])
            ->get()
            ->map(function ($appointment) {
                return [
                    'id' => 'appt-'.$appointment->id,
                    'title' => $appointment->title ?? 'Appointment',
                    'start' => Carbon::parse($appointment->start_time)->tz('UTC')->toIso8601String(),
                    'end' => Carbon::parse($appointment->end_time)->tz('UTC')->toIso8601String(),
                    'type' => 'appointment',
                    'status' => $appointment->status,
                    'patient' => $appointment->patient ? $appointment->patient->name : 'Unknown',
                    'doctor' => $appointment->doctor ? $appointment->doctor->name : 'Unknown',
                ];
            });

        // Fetch Visitors
        $visitors = Visitor::whereBetween('visit_date', [$startDate, $endDate])
            ->get()
            ->map(function ($visitor) {
                // Assuming visitors have a specific time, otherwise default to all day or business hours
                // If visit_time exists, combine with visit_date
                $start = $visitor->visit_date;
                if (! empty($visitor->check_in_time)) {
                    $start = Carbon::parse($visitor->visit_date.' '.$visitor->check_in_time)->toDateTimeString();
                }

                // End time defaults to 1 hour after start if not specified
                $end = Carbon::parse($start)->addHour()->toDateTimeString();
                if (! empty($visitor->check_out_time)) {
                    $end = Carbon::parse($visitor->visit_date.' '.$visitor->check_out_time)->toDateTimeString();
                }

                return [
                    'id' => 'vis-'.$visitor->id,
                    'title' => 'Visitor: '.$visitor->name,
                    'start' => Carbon::parse($start)->tz('UTC')->toIso8601String(),
                    'end' => Carbon::parse($end)->tz('UTC')->toIso8601String(),
                    'type' => 'visitor',
                    'status' => $visitor->status,
                    'purpose' => $visitor->purpose,
                ];
            });

        $events = $appointments->concat($visitors);

        return $this->sendResponse($events, 'Calendar events retrieved successfully.');
    }
}
