<?php

namespace App\Http\Controllers\API\V1\Tech;

use App\Http\Controllers\API\BaseController;
use App\Models\MaintenanceSchedule;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MaintenanceController extends BaseController
{
    /**
     * Display a listing of the maintenance schedules.
     */
    public function index(Request $request)
    {
        $query = MaintenanceSchedule::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $schedules = $query->orderBy('start_time', 'desc')->paginate(10);

        return $this->sendResponse($schedules, 'Maintenance schedules retrieved successfully.');
    }

    /**
     * Store a newly created maintenance schedule request.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
            'notify_users' => 'boolean',
            'affected_services' => 'nullable|array',
            'affected_services.*' => 'string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $schedule = MaintenanceSchedule::create([
            'title' => $request->title,
            'description' => $request->description,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'status' => 'pending', // Must be approved by admin
            'requested_by' => Auth::id(),
            'notify_users' => $request->notify_users ?? false,
            'affected_services' => $request->affected_services,
        ]);

        UserActivity::create([
            'user_id' => Auth::id(),
            'activity_type' => 'maintenance_request_created',
            'description' => "Requested maintenance: {$schedule->title}",
            'metadata' => ['schedule_id' => $schedule->id],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse($schedule, 'Maintenance schedule requested successfully. Pending Admin approval.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $schedule = MaintenanceSchedule::find($id);

        if (! $schedule) {
            return $this->sendError('Maintenance schedule not found.');
        }

        return $this->sendResponse($schedule, 'Maintenance schedule retrieved successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $schedule = MaintenanceSchedule::find($id);

        if (! $schedule) {
            return $this->sendError('Maintenance schedule not found.');
        }

        // Only allow editing if pending or if user is admin (but this is tech controller)
        // Tech can edit their own pending requests
        if ($schedule->status !== 'pending' && ! Auth::user()->hasRole('admin')) {
            return $this->sendError('Cannot edit maintenance schedule that is already '.$schedule->status);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'date|after:now',
            'end_time' => 'date|after:start_time',
            'notify_users' => 'boolean',
            'affected_services' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $schedule->update($request->all());

        return $this->sendResponse($schedule, 'Maintenance schedule updated successfully.');
    }

    /**
     * Remove the specified resource from storage (Cancel).
     */
    public function destroy($id)
    {
        $schedule = MaintenanceSchedule::find($id);

        if (! $schedule) {
            return $this->sendError('Maintenance schedule not found.');
        }

        $schedule->status = 'cancelled';
        $schedule->save();
        $schedule->delete(); // Soft delete

        return $this->sendResponse([], 'Maintenance schedule cancelled and removed.');
    }
}
