<?php

namespace App\Http\Controllers\API\V1\HR;

use App\Http\Controllers\API\BaseController;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeaveController extends BaseController
{
    public function index(Request $request)
    {
        $leaves = LeaveRequest::with('user')
            ->when($request->status, function ($q, $status) {
                return $q->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return $this->sendResponse($leaves, 'Leave requests retrieved.');
    }

    public function update(Request $request, $id)
    {
        $leave = LeaveRequest::find($id);

        if (! $leave) {
            return $this->sendError('Leave request not found.');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected',
            'rejection_reason' => 'nullable|required_if:status,rejected|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $leave->update([
            'status' => $request->status,
            'approved_by' => auth()->id(), // Assuming HR approves
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        return $this->sendResponse($leave, 'Leave request updated.');
    }
}
