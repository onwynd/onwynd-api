<?php

namespace App\Http\Controllers\ClinicalAdvisor;

use App\Http\Controllers\Controller;
use App\Models\AIChat;
use App\Models\Therapy\SessionReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SessionReviewController extends Controller
{
    /**
     * List sessions requiring review
     */
    public function index(Request $request)
    {
        $advisor = $request->clinical_advisor;

        $query = SessionReview::query();

        // ETHICS GUARD: Clinical Advisor cannot review their own patients
        $this->applyEthicsGuard($query);

        // Filter by status
        if ($request->has('status')) {
            $query->where('review_status', $request->status);
        } else {
            // Default: Show pending and flagged
            $query->whereIn('review_status', ['pending', 'flagged']);
        }

        // Filter by risk level
        if ($request->has('risk_level')) {
            $query->where('risk_level', $request->risk_level);
        }

        // Pagination
        $reviews = $query->with(['therapySession', 'therapist', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($reviews);
    }

    /**
     * Get distress queue - high-risk AI conversations requiring clinical review
     */
    public function distressQueue(Request $request)
    {
        try {
            // Query AI conversations with crisis keywords that require clinical review
            $query = AIChat::where('contains_crisis_keywords', true)
                ->where('requires_clinical_review', true)
                ->whereNull('reviewed_at');

            // ETHICS GUARD: Clinical Advisor cannot review their own patients
            // For AI conversations, we check if the user (patient) is currently assigned
            // to this clinical advisor as a therapist.
            $patientIds = \App\Models\TherapySession::where('therapist_id', auth()->id())
                ->pluck('patient_id')
                ->unique();

            $query->whereNotIn('user_id', $patientIds);

            $distressConversations = $query->with(['user' => function ($query) {
                // Anonymize user data - hash the member ID
                $query->select('id', 'organization_id')
                    ->addSelect(DB::raw('CONCAT("Member #", MD5(id)) as anonymized_id'));
            }])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            // Transform the data to match expected frontend format
            $transformed = $distressConversations->map(function ($conversation) {
                return [
                    'id' => $conversation->id,
                    'session_id' => $conversation->session_id,
                    'member_id' => $conversation->user ? 'Member #'.substr(md5($conversation->user->id), 0, 8) : 'Anonymous',
                    'organization_id' => $conversation->user->organization_id ?? null,
                    'risk_level' => $conversation->risk_level ?? 'high',
                    'flagged_at' => $conversation->created_at->toIso8601String(),
                    'message_preview' => substr($conversation->message, 0, 100).'...',
                    'resources_shown' => $conversation->metadata['resources_shown'] ?? false,
                    'type' => 'ai_conversation',
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformed,
                'pagination' => [
                    'current_page' => $distressConversations->currentPage(),
                    'total_pages' => $distressConversations->lastPage(),
                    'total_items' => $distressConversations->total(),
                    'per_page' => $distressConversations->perPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch distress queue', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch distress queue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get details of a specific review
     */
    public function show(string $id)
    {
        $query = SessionReview::with(['therapySession', 'therapist', 'user', 'actions', 'reviewedBy']);

        // ETHICS GUARD
        $this->applyEthicsGuard($query);

        $review = $query->findOrFail($id);

        return response()->json($review);
    }

    /**
     * Approve a session review
     */
    public function approve(Request $request, string $id)
    {
        $request->validate([
            'notes' => 'nullable|string',
        ]);

        $advisor = $request->clinical_advisor;

        $query = SessionReview::query();
        $this->applyEthicsGuard($query);
        $review = $query->findOrFail($id);

        $advisor->approveReview($review, $request->notes);

        return response()->json(['message' => 'Review approved', 'review' => $review]);
    }

    /**
     * Flag a session review
     */
    public function flag(Request $request, string $id)
    {
        $request->validate([
            'reason' => 'required|string',
            'priority' => 'in:low,normal,urgent,critical',
        ]);

        $advisor = $request->clinical_advisor;

        $query = SessionReview::query();
        $this->applyEthicsGuard($query);
        $review = $query->findOrFail($id);

        $advisor->flagReview($review, $request->reason, $request->priority ?? 'normal');

        return response()->json(['message' => 'Review flagged', 'review' => $review]);
    }

    /**
     * Escalate to crisis
     */
    public function escalate(Request $request, string $id)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        $advisor = $request->clinical_advisor;

        $query = SessionReview::query();
        $this->applyEthicsGuard($query);
        $review = $query->findOrFail($id);

        $advisor->escalateToCrisis($review, $request->reason);

        return response()->json(['message' => 'Review escalated to crisis', 'review' => $review]);
    }

    /**
     * Resolve a distress queue item (AI conversation)
     */
    public function resolveDistressQueueItem(Request $request, string $id)
    {
        $request->validate([
            'notes' => 'nullable|string',
            'resolution_type' => 'required|in:resolved,escalated,flagged',
        ]);

        try {
            $query = AIChat::query();

            // ETHICS GUARD: Clinical Advisor cannot review their own patients
            $patientIds = \App\Models\TherapySession::where('therapist_id', auth()->id())
                ->pluck('patient_id')
                ->unique();

            $query->whereNotIn('user_id', $patientIds);

            $conversation = $query->findOrFail($id);

            // Update the conversation to mark it as reviewed
            $conversation->update([
                'requires_clinical_review' => false,
                'reviewed_at' => now(),
                'reviewed_by' => $request->clinical_advisor->id,
                'clinical_notes' => $request->notes,
                'resolution_type' => $request->resolution_type,
            ]);

            // Log the resolution action
            Log::info('Distress queue item resolved', [
                'conversation_id' => $id,
                'advisor_id' => $request->clinical_advisor->id,
                'resolution_type' => $request->resolution_type,
                'notes' => $request->notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Distress queue item resolved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to resolve distress queue item', [
                'conversation_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve distress queue item',
            ], 500);
        }
    }

    /**
     * ETHICS GUARD: Clinical Advisor cannot review their own patients.
     * Applies to SessionReview queries.
     */
    private function applyEthicsGuard($query)
    {
        $userId = auth()->id();

        // Exclude sessions where the authenticated user is the therapist
        $query->where('therapist_id', '!=', $userId);

        // Also exclude patients of the authenticated user
        $patientIds = \App\Models\TherapySession::where('therapist_id', $userId)
            ->pluck('patient_id')
            ->unique();

        $query->whereNotIn('user_id', $patientIds);
    }
}
