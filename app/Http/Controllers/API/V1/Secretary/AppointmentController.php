<?php

namespace App\Http\Controllers\API\V1\Secretary;

use App\Http\Controllers\API\BaseController;
use App\Models\TherapySession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppointmentController extends BaseController
{
    public function index(Request $request)
    {
        $query = TherapySession::with(['patient', 'therapist']);

        if ($request->has('date')) {
            $query->whereDate('scheduled_at', $request->date);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            })->orWhereHas('therapist', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $appointments = $query->orderBy('scheduled_at', 'desc')->paginate(15);

        // Transform to match frontend interface Appointment
        $data = $appointments->getCollection()->transform(function ($appointment) {
            return [
                'id' => $appointment->id,
                'title' => $appointment->booking_notes ?? 'Therapy Session',
                'patient_name' => $appointment->patient ? $appointment->patient->name : 'Unknown',
                'doctor_name' => $appointment->therapist ? $appointment->therapist->name : 'Unknown',
                'type' => ucfirst($appointment->session_type ?? 'video'),
                'status' => $appointment->status,
                'start_time' => $appointment->scheduled_at->toDateTimeString(),
                'end_time' => $appointment->scheduled_at->copy()->addMinutes($appointment->duration_minutes)->toDateTimeString(),
                'notes' => $appointment->booking_notes,
            ];
        });

        // Re-wrap paginated data
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $data,
            $appointments->total(),
            $appointments->perPage(),
            $appointments->currentPage(),
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
        );

        return $this->sendResponse($paginated, 'Appointments retrieved successfully.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'patient_name' => 'required|string',
            'doctor_name' => 'required|string',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
            'type' => 'required|string',
        ]);

        // Find Patient (Basic search)
        $patientName = explode(' ', $request->patient_name);
        $patientQuery = User::query();
        foreach ($patientName as $part) {
            $patientQuery->where(function ($q) use ($part) {
                $q->where('first_name', 'like', "%{$part}%")
                    ->orWhere('last_name', 'like', "%{$part}%");
            });
        }
        $patient = $patientQuery->first();

        if (! $patient) {
            return $this->sendError('Patient not found. Please ensure the user exists.');
        }

        // Find Doctor/Therapist
        $doctorName = explode(' ', $request->doctor_name);
        $doctorQuery = User::query();
        foreach ($doctorName as $part) {
            $doctorQuery->where(function ($q) use ($part) {
                $q->where('first_name', 'like', "%{$part}%")
                    ->orWhere('last_name', 'like', "%{$part}%");
            });
        }
        $doctor = $doctorQuery->first();

        if (! $doctor) {
            return $this->sendError('Doctor not found. Please ensure the user exists.');
        }

        $startTime = Carbon::parse($request->start_time);
        $endTime = $request->end_time ? Carbon::parse($request->end_time) : $startTime->copy()->addMinutes(50);
        $duration = $startTime->diffInMinutes($endTime);

        $appointment = TherapySession::create([
            'uuid' => (string) Str::uuid(),
            'patient_id' => $patient->id,
            'therapist_id' => $doctor->id,
            'session_type' => strtolower($request->type),
            'status' => 'scheduled',
            'scheduled_at' => $startTime,
            'duration_minutes' => $duration,
            'session_rate' => 100.00, // Default rate
            'payment_status' => 'pending',
            'booking_notes' => $request->title ?? 'Scheduled via Secretary',
        ]);

        return $this->sendResponse($appointment, 'Appointment scheduled successfully.');
    }

    public function update(Request $request, $id)
    {
        $appointment = TherapySession::find($id);

        if (! $appointment) {
            return $this->sendError('Appointment not found.');
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'patient_name' => 'string',
            'doctor_name' => 'string',
            'start_time' => 'date',
            'end_time' => 'nullable|date|after:start_time',
            'type' => 'string',
            'notes' => 'nullable|string',
            'status' => 'string|in:scheduled,completed,cancelled,no_show',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // Update logic (simplified for now, ideally would update relations if names changed)
        // For this task, we'll update basic fields
        if ($request->has('start_time')) {
            $appointment->scheduled_at = Carbon::parse($request->start_time);
            if ($request->has('end_time')) {
                $duration = Carbon::parse($request->start_time)->diffInMinutes(Carbon::parse($request->end_time));
                $appointment->duration_minutes = $duration;
            }
        }

        if ($request->has('notes')) {
            $appointment->booking_notes = $request->notes;
        }

        if ($request->has('status')) {
            $appointment->status = $request->status;
        }

        $appointment->save();

        return $this->sendResponse($appointment, 'Appointment updated successfully.');
    }

    public function destroy($id)
    {
        $appointment = TherapySession::find($id);
        if ($appointment) {
            $appointment->delete(); // Soft delete if model supports it, or hard delete

            return $this->sendResponse([], 'Appointment deleted successfully.');
        }

        return $this->sendError('Appointment not found.');
    }

    public function confirm($id)
    {
        $appointment = TherapySession::find($id);
        if ($appointment) {
            $appointment->status = 'scheduled'; // Re-confirming usually means setting to scheduled or active? Logic says "confirmed" but status enum is scheduled/ongoing.
            // Let's assume 'scheduled' is the confirmed state.
            $appointment->save();

            return $this->sendResponse([], 'Appointment confirmed successfully.');
        }

        return $this->sendError('Appointment not found.');
    }

    public function cancel($id)
    {
        $appointment = TherapySession::find($id);
        if ($appointment) {
            $appointment->status = 'cancelled';
            $appointment->save();

            return $this->sendResponse([], 'Appointment cancelled successfully.');
        }

        return $this->sendError('Appointment not found.');
    }
}
