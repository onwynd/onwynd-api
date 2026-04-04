<?php

namespace App\Http\Controllers\API\V1\Assessment;

use App\Http\Controllers\Controller;
use App\Models\UserAssessmentResult;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AssessmentResultController extends Controller
{
    /**
     * GET /api/v1/assessments/results/recent
     */
    public function recent(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $limit = (int) $request->get('limit', 5);

            $results = UserAssessmentResult::where('user_id', $user->id)
                ->with('assessment')
                ->orderByDesc('completed_at')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Recent assessment results retrieved',
                'data' => $results,
            ]);
        } catch (Exception $e) {
            Log::error('AssessmentResult: recent failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to retrieve recent results'], 400);
        }
    }

    /**
     * DELETE /api/v1/assessments/results/{id}
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();

        $result = UserAssessmentResult::where('id', $id)
            ->orWhere('uuid', $id)
            ->first();

        if (! $result) {
            return response()->json(['success' => false, 'message' => 'Assessment result not found'], 404);
        }

        if ($result->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $result->delete();

            return response()->json(['success' => true, 'message' => 'Assessment result deleted']);
        } catch (Exception $e) {
            Log::error('AssessmentResult: delete failed', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to delete assessment result'], 400);
        }
    }
}
