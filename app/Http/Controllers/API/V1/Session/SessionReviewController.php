<?php

namespace App\Http\Controllers\API\V1\Session;

use App\Http\Controllers\Controller;
use App\Models\TherapySession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionReviewController extends Controller
{
    /**
     * Submit a patient star rating and review for a completed therapy session.
     *
     * POST /api/v1/sessions/{uuid}/review
     */
    public function store(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'rating'      => 'required|integer|min:1|max:5',
            'review_text' => 'nullable|string|max:1000',
        ]);

        $session = TherapySession::where('uuid', $uuid)
            ->where('patient_id', Auth::id())
            ->where('status', 'completed')
            ->firstOrFail();

        if ($session->reviewed_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this session.',
            ], 409);
        }

        $session->update([
            'rating'      => $request->integer('rating'),
            'review_text' => $request->input('review_text'),
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your feedback.',
        ]);
    }
}
