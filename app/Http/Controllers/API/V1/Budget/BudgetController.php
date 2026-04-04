<?php

namespace App\Http\Controllers\API\V1\Budget;

use App\Http\Controllers\Controller;
use App\Models\DepartmentBudget;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Budget approval workflow:
 *
 *   [marketing/sales] → submit → [COO] → approve → [CEO] → approve → [Finance] → approve → done
 *                                    ↓                   ↓                    ↓
 *                                 reject              reject               reject
 *
 * Role access matrix:
 *   marketing / sales  : create, read-own, update-draft, submit, respond-to-query
 *   coo                : create, read-pending-coo, approve-coo, reject
 *   ceo                : read-pending-ceo + queried, approve-ceo, query, reject
 *   finance / cfo      : read-pending-finance, approve-finance, reject
 *   admin / super_admin: full read + reject
 */
class BudgetController extends Controller
{
    /**
     * GET /api/v1/budgets
     * Returns budgets filtered by what the caller is allowed to see.
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        $roles = $this->userRoles($user);
        $query = DepartmentBudget::with(['submittedBy:id,first_name,last_name',
                                         'approvedByCoo:id,first_name,last_name',
                                         'approvedByCeo:id,first_name,last_name',
                                         'approvedByFinance:id,first_name,last_name',
                                         'rejectedBy:id,first_name,last_name'])
                                 ->latest();

        if ($this->hasRole($roles, ['admin', 'super_admin', 'founder'])) {
            // see all
        } elseif ($this->hasRole($roles, ['coo'])) {
            $query->where('status', 'pending_coo');
        } elseif ($this->hasRole($roles, ['ceo', 'president'])) {
            $query->whereIn('status', ['pending_ceo', 'queried']);
        } elseif ($this->hasRole($roles, ['finance', 'cfo'])) {
            $query->where('status', 'pending_finance');
        } else {
            // Submitters see their own department's budgets
            $query->where('submitted_by', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * POST /api/v1/budgets
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department'       => 'required|string|max:100',
            'category'         => 'required|string|max:100',
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string|max:2000',
            'amount_requested' => 'required|numeric|min:1',
            'currency'         => 'nullable|string|size:3',
            'period'           => 'required|string|max:20',
        ]);

        $budget = DepartmentBudget::create([
            ...$validated,
            'submitted_by' => $request->user()->id,
            'status'       => 'draft',
        ]);

        return response()->json($budget, 201);
    }

    /**
     * GET /api/v1/budgets/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $budget = DepartmentBudget::with([
            'submittedBy:id,first_name,last_name,email',
            'approvedByCoo:id,first_name,last_name',
            'approvedByCeo:id,first_name,last_name',
            'approvedByFinance:id,first_name,last_name',
            'rejectedBy:id,first_name,last_name',
            'expenses',
        ])->findOrFail($id);

        $this->authorizeView($request->user(), $budget);

        return response()->json($budget);
    }

    /**
     * PUT /api/v1/budgets/{id}
     * Only allowed while in 'draft' status.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $budget = DepartmentBudget::findOrFail($id);

        abort_if($budget->status !== 'draft', 403, 'Only draft budgets can be edited.');
        abort_if($budget->submitted_by !== $request->user()->id, 403);

        $validated = $request->validate([
            'title'            => 'sometimes|string|max:255',
            'description'      => 'nullable|string|max:2000',
            'amount_requested' => 'sometimes|numeric|min:1',
            'category'         => 'sometimes|string|max:100',
            'period'           => 'sometimes|string|max:20',
        ]);

        $budget->update($validated);

        return response()->json($budget);
    }

    /**
     * DELETE /api/v1/budgets/{id}
     * Only allowed while in 'draft' status.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $budget = DepartmentBudget::findOrFail($id);

        abort_if($budget->status !== 'draft', 403, 'Only draft budgets can be deleted.');
        abort_if($budget->submitted_by !== $request->user()->id, 403);

        $budget->delete();

        return response()->json(['message' => 'Budget request deleted.']);
    }

    /**
     * POST /api/v1/budgets/{id}/submit
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        $budget = DepartmentBudget::findOrFail($id);

        abort_if($budget->submitted_by !== $request->user()->id, 403);
        abort_if($budget->status !== 'draft', 422, 'Budget is not in draft status.');

        $budget->submit($request->user());

        // Notify all COO users that a new budget needs their approval
        $this->notifyByRole(['coo'], 'budget_submitted', 'New Budget Request',
            "{$request->user()->first_name} {$request->user()->last_name} submitted \"{$budget->title}\" ({$budget->department}) for COO approval.",
            "/coo/budget-approvals",
            ['budget_id' => $budget->id]
        );

        return response()->json(['message' => 'Budget submitted for COO approval.', 'budget' => $budget]);
    }

    /**
     * POST /api/v1/budgets/{id}/approve/coo
     */
    public function approveCoo(Request $request, int $id): JsonResponse
    {
        $this->requireRole($request->user(), ['coo', 'admin', 'super_admin']);

        $budget = DepartmentBudget::findOrFail($id);
        abort_if($budget->status !== 'pending_coo', 422, 'Budget is not awaiting COO approval.');

        $validated = $request->validate(['notes' => 'nullable|string|max:1000']);
        $budget->approveByCoo($request->user(), $validated['notes'] ?? null);

        // Notify all CEO/President users that a budget is ready for their approval
        $this->notifyByRole(['ceo', 'president'], 'budget_pending_ceo', 'Budget Awaiting Your Approval',
            "COO approved \"{$budget->title}\" ({$budget->department}). Ready for your review.",
            "/ceo/budget-approvals",
            ['budget_id' => $budget->id]
        );

        return response()->json(['message' => 'Approved by COO. Forwarded to CEO.', 'budget' => $budget]);
    }

    /**
     * POST /api/v1/budgets/{id}/approve/ceo
     */
    public function approveCeo(Request $request, int $id): JsonResponse
    {
        $this->requireRole($request->user(), ['ceo', 'president', 'admin', 'super_admin']);

        $budget = DepartmentBudget::findOrFail($id);
        abort_if($budget->status !== 'pending_ceo', 422, 'Budget is not awaiting CEO approval.');

        $validated = $request->validate(['notes' => 'nullable|string|max:1000']);
        $budget->approveByCeo($request->user(), $validated['notes'] ?? null);

        // Notify Finance/CFO that a budget is ready for final approval
        $this->notifyByRole(['finance', 'cfo'], 'budget_pending_finance', 'Budget Ready for Final Approval',
            "CEO approved \"{$budget->title}\" ({$budget->department}). Please complete the final finance approval.",
            "/finance/budget-approvals",
            ['budget_id' => $budget->id]
        );

        return response()->json(['message' => 'Approved by CEO. Forwarded to Finance.', 'budget' => $budget]);
    }

    /**
     * POST /api/v1/budgets/{id}/approve/finance
     */
    public function approveFinance(Request $request, int $id): JsonResponse
    {
        $this->requireRole($request->user(), ['finance', 'cfo', 'admin', 'super_admin']);

        $budget = DepartmentBudget::findOrFail($id);
        abort_if($budget->status !== 'pending_finance', 422, 'Budget is not awaiting Finance approval.');

        $validated = $request->validate(['notes' => 'nullable|string|max:1000']);
        $budget->approveByFinance($request->user(), $validated['notes'] ?? null);

        // Notify the original submitter that their budget is fully approved
        $this->notifyUser($budget->submitted_by, 'budget_approved', 'Budget Approved',
            "Your budget request \"{$budget->title}\" has been fully approved by Finance.",
            "/budget",
            ['budget_id' => $budget->id]
        );

        return response()->json(['message' => 'Budget fully approved and deducted from system.', 'budget' => $budget]);
    }

    /**
     * POST /api/v1/budgets/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $budget = DepartmentBudget::findOrFail($id);
        abort_if(
            !in_array($budget->status, ['pending_coo', 'pending_ceo', 'queried', 'pending_finance'], true),
            422,
            'Budget cannot be rejected at this stage.'
        );

        $validated = $request->validate(['reason' => 'required|string|min:10|max:1000']);
        $budget->reject($request->user(), $validated['reason']);

        // Notify the original submitter their budget was rejected
        $this->notifyUser($budget->submitted_by, 'budget_rejected', 'Budget Request Rejected',
            "Your budget request \"{$budget->title}\" was rejected. Reason: {$validated['reason']}",
            "/budget",
            ['budget_id' => $budget->id]
        );

        return response()->json(['message' => 'Budget rejected.', 'budget' => $budget]);
    }

    /**
     * POST /api/v1/budgets/{id}/query/ceo
     * CEO sends the budget back to the creator with a query and optional counter-amount.
     */
    public function queryCeo(Request $request, int $id): JsonResponse
    {
        $this->requireRole($request->user(), ['ceo', 'president', 'admin', 'super_admin']);

        $budget = DepartmentBudget::findOrFail($id);
        abort_if($budget->status !== 'pending_ceo', 422, 'Budget is not awaiting CEO approval.');

        $validated = $request->validate([
            'query_notes'      => 'required|string|min:10|max:2000',
            'suggested_amount' => 'nullable|numeric|min:1',
        ]);

        $budget->queryCeo(
            $request->user(),
            $validated['query_notes'],
            isset($validated['suggested_amount']) ? (float) $validated['suggested_amount'] : null
        );

        // Notify the original submitter the CEO has a query on their budget
        $this->notifyUser($budget->submitted_by, 'budget_queried', 'CEO Query on Your Budget',
            "The CEO has a query on your budget request \"{$budget->title}\". Please review and respond.",
            "/budget",
            ['budget_id' => $budget->id]
        );

        return response()->json(['message' => 'Budget queried. Creator has been notified to respond.', 'budget' => $budget]);
    }

    /**
     * POST /api/v1/budgets/{id}/respond
     * Original submitter responds to a CEO query.
     */
    public function respondToQuery(Request $request, int $id): JsonResponse
    {
        $budget = DepartmentBudget::findOrFail($id);

        abort_if($budget->submitted_by !== $request->user()->id, 403, 'Only the original submitter can respond.');
        abort_if($budget->status !== 'queried', 422, 'Budget is not in queried state.');

        $validated = $request->validate([
            'response' => 'required|string|min:10|max:2000',
        ]);

        $budget->respondToQuery($validated['response']);

        // Notify CEO/President that the creator has responded and the budget is back in their queue
        $this->notifyByRole(['ceo', 'president'], 'budget_creator_responded', 'Budget Response Received',
            "Creator responded to your query on \"{$budget->title}\". Budget is back in your approval queue.",
            "/ceo/budget-approvals",
            ['budget_id' => $budget->id]
        );

        return response()->json(['message' => 'Response submitted. Budget returned to CEO for review.', 'budget' => $budget]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function userRoles($user): array
    {
        // Uses the singular BelongsTo role() relationship (slug-based)
        if (!$user) {
            return [];
        }
        $role = $user->role;
        return $role ? [$role->slug] : [];
    }

    private function hasRole(array $roles, array $check): bool
    {
        return !empty(array_intersect($roles, $check));
    }

    private function requireRole($user, array $allowed): void
    {
        $roles = $this->userRoles($user);
        abort_if(!$this->hasRole($roles, $allowed), 403, 'Insufficient role.');
    }

    private function authorizeView($user, DepartmentBudget $budget): void
    {
        $roles = $this->userRoles($user);
        $isApprover = $this->hasRole($roles, ['coo', 'ceo', 'president', 'finance', 'cfo', 'admin', 'super_admin', 'founder']);
        $isOwner    = $budget->submitted_by === $user->id;

        abort_if(!$isApprover && !$isOwner, 403);
    }

    /**
     * Create an in-app notification for every user whose role slug is in $roleSlugs.
     * Failures are caught and logged so a notification issue never aborts the workflow.
     *
     * @param string[] $roleSlugs
     * @param array<string,mixed> $data
     */
    private function notifyByRole(array $roleSlugs, string $type, string $title, string $message, string $actionUrl, array $data = []): void
    {
        try {
            $recipients = User::whereHas('role', fn ($q) => $q->whereIn('slug', $roleSlugs))
                ->where('is_active', true)
                ->pluck('id');

            foreach ($recipients as $userId) {
                \App\Models\Notification::create([
                    'user_id'    => $userId,
                    'type'       => $type,
                    'title'      => $title,
                    'message'    => $message,
                    'action_url' => $actionUrl,
                    'data'       => $data,
                    'is_read'    => false,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('BudgetController: notifyByRole failed', [
                'roles'   => $roleSlugs,
                'type'    => $type,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create an in-app notification for a single user by primary key.
     * Failures are caught and logged so a notification issue never aborts the workflow.
     *
     * @param array<string,mixed> $data
     */
    private function notifyUser(int $userId, string $type, string $title, string $message, string $actionUrl, array $data = []): void
    {
        try {
            \App\Models\Notification::create([
                'user_id'    => $userId,
                'type'       => $type,
                'title'      => $title,
                'message'    => $message,
                'action_url' => $actionUrl,
                'data'       => $data,
                'is_read'    => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('BudgetController: notifyUser failed', [
                'user_id' => $userId,
                'type'    => $type,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
