<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeedbackController extends BaseController
{
    /**
     * Get all feedback with optional filtering
     */
    public function index(Request $request)
    {
        $query = Feedback::with('user');

        // Filter by status
        if ($request->has('status')) {
            $query->byStatus($request->input('status'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->byType($request->input('type'));
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date'));
        }

        // Get recent feedback first
        $query->recent();

        $feedback = $query->paginate($request->input('per_page', 20));

        return $this->sendResponse([
            'feedback' => $feedback->items(),
            'pagination' => [
                'total' => $feedback->total(),
                'per_page' => $feedback->perPage(),
                'current_page' => $feedback->currentPage(),
                'last_page' => $feedback->lastPage(),
            ],
            'summary' => $this->getFeedbackSummary(),
        ], 'Feedback retrieved successfully.');
    }

    /**
     * Get feedback summary statistics
     */
    private function getFeedbackSummary()
    {
        return [
            'total' => Feedback::count(),
            'pending' => Feedback::byStatus('pending')->count(),
            'reviewed' => Feedback::byStatus('reviewed')->count(),
            'resolved' => Feedback::byStatus('resolved')->count(),
            'by_type' => [
                'bug' => Feedback::byType('bug')->count(),
                'feature' => Feedback::byType('feature')->count(),
                'general' => Feedback::byType('general')->count(),
            ],
            'average_rating' => Feedback::whereNotNull('rating')->avg('rating') ?: 0,
        ];
    }

    /**
     * Get single feedback item
     */
    public function show($id)
    {
        $feedback = Feedback::with('user')->find($id);

        if (! $feedback) {
            return $this->sendError('Feedback not found.', [], 404);
        }

        return $this->sendResponse(['feedback' => $feedback], 'Feedback retrieved successfully.');
    }

    /**
     * Update feedback status
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,reviewed,resolved',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $feedback = Feedback::find($id);

        if (! $feedback) {
            return $this->sendError('Feedback not found.', [], 404);
        }

        $feedback->status = $request->input('status');
        $feedback->save();

        return $this->sendResponse(['feedback' => $feedback], 'Feedback status updated successfully.');
    }

    /**
     * Delete feedback
     */
    public function destroy($id)
    {
        $feedback = Feedback::find($id);

        if (! $feedback) {
            return $this->sendError('Feedback not found.', [], 404);
        }

        $feedback->delete();

        return $this->sendResponse([], 'Feedback deleted successfully.');
    }
}
