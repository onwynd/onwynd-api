<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Jobs\ProcessMaintenanceNotifications;
use App\Models\Admin\AdminLog;
use App\Models\MaintenanceSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MaintenanceController extends BaseController
{
    /**
     * List all maintenance requests (pending first).
     */
    public function index(Request $request)
    {
        $query = MaintenanceSchedule::with(['requester', 'approver']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $schedules = $query->orderByRaw("FIELD(status, 'pending', 'scheduled', 'in_progress', 'completed', 'cancelled', 'rejected')")
            ->orderBy('start_time', 'desc')
            ->paginate(10);

        return $this->sendResponse($schedules, 'Maintenance schedules retrieved successfully.');
    }

    /**
     * Approve a maintenance request.
     */
    public function approve(Request $request, $id)
    {
        $schedule = MaintenanceSchedule::find($id);

        if (! $schedule) {
            return $this->sendError('Maintenance schedule not found.');
        }

        if ($schedule->status !== 'pending') {
            return $this->sendError('This schedule is already '.$schedule->status);
        }

        $schedule->status = 'scheduled';
        $schedule->approved_by = Auth::id();
        $schedule->save();

        // Send notifications if enabled
        if ($schedule->notify_users && ! $schedule->notification_sent) {
            ProcessMaintenanceNotifications::dispatch($schedule);

            $schedule->notification_sent = true;
            $schedule->save();
        }

        return $this->sendResponse($schedule, 'Maintenance schedule approved and scheduled.');
    }

    /**
     * Reject a maintenance request.
     */
    public function reject(Request $request, $id)
    {
        $schedule = MaintenanceSchedule::find($id);

        if (! $schedule) {
            return $this->sendError('Maintenance schedule not found.');
        }

        if ($schedule->status !== 'pending') {
            return $this->sendError('This schedule is already '.$schedule->status);
        }

        $schedule->status = 'rejected';
        $schedule->approved_by = Auth::id(); // Rejected by admin
        $schedule->save();

        AdminLog::create([
            'user_id' => Auth::id(),
            'action' => 'reject_maintenance',
            'target_type' => MaintenanceSchedule::class,
            'target_id' => $schedule->id,
            'details' => json_encode(['title' => $schedule->title]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse($schedule, 'Maintenance schedule rejected.');
    }

    /**
     * Mark as completed manually.
     */
    public function complete($id)
    {
        $schedule = MaintenanceSchedule::find($id);

        if (! $schedule) {
            return $this->sendError('Maintenance schedule not found.');
        }

        $schedule->status = 'completed';
        $schedule->save();

        AdminLog::create([
            'user_id' => Auth::id(),
            'action' => 'complete_maintenance',
            'target_type' => MaintenanceSchedule::class,
            'target_id' => $schedule->id,
            'details' => json_encode(['title' => $schedule->title]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $this->sendResponse($schedule, 'Maintenance schedule marked as completed.');
    }
}
