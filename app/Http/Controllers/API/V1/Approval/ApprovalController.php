<?php

namespace App\Http\Controllers\API\V1\Approval;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Services\ApprovalEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalEngine $engine) {}

    /**
     * GET /api/v1/approvals/inbox
     * All pending steps where the auth user is the approver.
     */
    public function inbox(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $steps = $this->engine->inboxFor($user);
        return response()->json($steps);
    }

    /**
     * GET /api/v1/approvals
     * All requests submitted by or involving the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $roleNames = $user->roles()->pluck('name')->toArray();
        $isAdmin   = !empty(array_intersect($roleNames, ['admin', 'super_admin', 'hr']));

        $query = ApprovalRequest::with([
            'requester:id,first_name,last_name,email',
            'steps.approver:id,first_name,last_name',
            'steps.actionedBy:id,first_name,last_name',
        ])->latest();

        if (!$isAdmin) {
            $query->where(function ($q) use ($user, $roleNames) {
                $q->where('requested_by', $user->id)
                  ->orWhereHas('steps', fn ($s) =>
                      $s->where('approver_id', $user->id)
                        ->orWhereIn('approver_role', $roleNames)
                  );
            });
        }

        if ($request->filled('type'))   $query->where('type', $request->type);
        if ($request->filled('status')) $query->where('status', $request->status);

        return response()->json($query->paginate(20));
    }

    /**
     * POST /api/v1/approvals
     * Initiate a new approval request.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'         => 'required|in:leave,budget,promotion,transfer,termination,expense,custom',
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string|max:2000',
            'metadata'     => 'nullable|array',
            'subject_type' => 'nullable|string|max:100',
            'subject_id'   => 'nullable|integer',
            'custom_steps' => 'required_if:type,custom|array|min:1',
            'custom_steps.*.label'       => 'required_with:custom_steps|string|max:100',
            'custom_steps.*.approver_id' => 'required_with:custom_steps|exists:users,id',
        ]);

        $subject = null;
        if (!empty($validated['subject_type']) && !empty($validated['subject_id'])) {
            $subject = app($validated['subject_type'])->find($validated['subject_id']);
        }

        $approvalRequest = $this->engine->initiate(
            type:        $validated['type'],
            title:       $validated['title'],
            requester:   $request->user(),
            meta:        array_merge($validated['metadata'] ?? [], ['description' => $validated['description'] ?? null]),
            subject:     $subject,
            customSteps: $validated['custom_steps'] ?? [],
        );

        return response()->json($approvalRequest->load('steps.approver:id,first_name,last_name'), 201);
    }

    /**
     * GET /api/v1/approvals/{uuid}
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $approval = ApprovalRequest::with([
            'requester:id,first_name,last_name,email',
            'steps.approver:id,first_name,last_name',
            'steps.actionedBy:id,first_name,last_name',
        ])->where('uuid', $uuid)->firstOrFail();

        $this->authorizeView($request->user(), $approval);

        return response()->json($approval);
    }

    /**
     * POST /api/v1/approvals/{uuid}/approve
     */
    public function approve(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate(['notes' => 'nullable|string|max:1000']);
        $approval  = ApprovalRequest::where('uuid', $uuid)->firstOrFail();
        $result    = $this->engine->approve($approval, $request->user(), $validated['notes'] ?? null);
        return response()->json($result->load('steps'));
    }

    /**
     * POST /api/v1/approvals/{uuid}/reject
     */
    public function reject(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|min:10|max:1000']);
        $approval  = ApprovalRequest::where('uuid', $uuid)->firstOrFail();
        $result    = $this->engine->reject($approval, $request->user(), $validated['reason']);
        return response()->json($result->load('steps'));
    }

    /**
     * POST /api/v1/approvals/{uuid}/review
     * Approver sends back to requester for more information.
     */
    public function requestReview(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate(['questions' => 'required|string|min:10|max:1000']);
        $approval  = ApprovalRequest::where('uuid', $uuid)->firstOrFail();
        $result    = $this->engine->requestReview($approval, $request->user(), $validated['questions']);
        return response()->json($result->load('steps'));
    }

    /**
     * POST /api/v1/approvals/{uuid}/respond
     * Requester responds to a "request review".
     */
    public function respond(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate(['response' => 'required|string|min:5|max:2000']);
        $approval  = ApprovalRequest::where('uuid', $uuid)->firstOrFail();
        $result    = $this->engine->respondToReview($approval, $request->user(), $validated['response']);
        return response()->json($result->load('steps'));
    }

    /**
     * POST /api/v1/approvals/{uuid}/cancel
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $approval = ApprovalRequest::where('uuid', $uuid)->firstOrFail();
        $result   = $this->engine->cancel($approval, $request->user());
        return response()->json($result);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function authorizeView($user, ApprovalRequest $approval): void
    {
        $roleNames = $user->roles()->pluck('name')->toArray();
        $isAdmin   = !empty(array_intersect($roleNames, ['admin', 'super_admin', 'hr']));
        $isRequester = $approval->requested_by === $user->id;
        $isApprover  = $approval->steps->contains(fn ($s) =>
            $s->approver_id === $user->id || in_array($s->approver_role, $roleNames, true)
        );

        abort_if(!$isAdmin && !$isRequester && !$isApprover, 403);
    }
}
