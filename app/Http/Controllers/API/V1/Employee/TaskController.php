<?php

namespace App\Http\Controllers\API\V1\Employee;

use App\Http\Controllers\API\BaseController;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TaskController extends BaseController
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $tasks = Task::where('assigned_to', $user->id)
            ->when($request->status, function ($q, $status) {
                return $q->where('status', $status);
            })
            ->paginate($request->get('per_page', 20));

        return $this->sendResponse($tasks, 'Tasks retrieved successfully.');
    }

    public function show($id)
    {
        $task = Task::where('assigned_to', Auth::id())->find($id);

        if (! $task) {
            return $this->sendError('Task not found.');
        }

        return $this->sendResponse($task, 'Task details retrieved.');
    }

    public function update(Request $request, $id)
    {
        $task = Task::where('assigned_to', Auth::id())->find($id);

        if (! $task) {
            return $this->sendError('Task not found.');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,in_progress,completed',
            'completion_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $task->update([
            'status' => $request->status,
            'completion_notes' => $request->completion_notes ?? $task->completion_notes,
        ]);

        return $this->sendResponse($task, 'Task updated successfully.');
    }
}
