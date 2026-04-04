<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Dispute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DisputeController extends BaseController
{
    public function index(Request $request)
    {
        $query = Dispute::with(['user:id,first_name,last_name,email'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $disputes = $query->paginate(20);

        return $this->sendResponse($disputes, 'Disputes retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $dispute = Dispute::find($id);

        if (! $dispute) {
            return $this->sendError('Dispute not found.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:open,under_review,resolved,closed',
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $dispute->update([
            'status' => $request->status,
            'resolution_notes' => $request->resolution_notes,
            'resolved_by' => in_array($request->status, ['resolved', 'closed']) ? $request->user()->id : $dispute->resolved_by,
            'resolved_at' => in_array($request->status, ['resolved', 'closed']) ? now() : $dispute->resolved_at,
        ]);

        return $this->sendResponse($dispute->fresh(), 'Dispute updated successfully.');
    }
}
