<?php

namespace App\Http\Controllers\API\V1\Employee;

use App\Http\Controllers\API\BaseController;
use App\Models\TimeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TimeSheetController extends BaseController
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $logs = TimeLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return $this->sendResponse($logs, 'Timesheet retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'task_id' => 'nullable|exists:tasks,id',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $log = TimeLog::create([
            'user_id' => Auth::id(),
            'task_id' => $request->task_id,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'description' => $request->description,
        ]);

        return $this->sendResponse($log, 'Time entry created successfully.');
    }

    public function clockIn(Request $request)
    {
        $user = Auth::user();

        // Check if already clocked in
        $activeLog = TimeLog::where('user_id', $user->id)
            ->whereNull('end_time')
            ->first();

        if ($activeLog) {
            return $this->sendError('You are already clocked in.');
        }

        $log = TimeLog::create([
            'user_id' => $user->id,
            'start_time' => now(),
            'description' => 'Clocked In',
        ]);

        return $this->sendResponse($log, 'Clocked in successfully.');
    }

    public function clockOut(Request $request)
    {
        $user = Auth::user();

        $activeLog = TimeLog::where('user_id', $user->id)
            ->whereNull('end_time')
            ->latest()
            ->first();

        if (! $activeLog) {
            return $this->sendError('You are not clocked in.');
        }

        $activeLog->update(['end_time' => now()]);

        return $this->sendResponse($activeLog, 'Clocked out successfully.');
    }
}
