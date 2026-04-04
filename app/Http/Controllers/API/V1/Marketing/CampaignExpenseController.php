<?php

namespace App\Http\Controllers\API\V1\Marketing;

use App\Http\Controllers\Controller;
use App\Models\CampaignExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CampaignExpenseController extends Controller
{
    /**
     * GET /api/v1/campaign-expenses
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $roles = $user->roles()->pluck('name')->toArray();

        $query = CampaignExpense::with(['submittedBy:id,first_name,last_name', 'reviewedBy:id,first_name,last_name', 'campaign:id,name'])
                                ->latest();

        // Finance/admin see all; submitters see their own
        $canSeeAll = !empty(array_intersect($roles, ['finance', 'cfo', 'admin', 'super_admin', 'founder', 'vp_marketing', 'coo', 'ceo', 'president']));
        if (!$canSeeAll) {
            $query->where('submitted_by', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('platform')) {
            $query->where('platform', $request->platform);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * POST /api/v1/campaign-expenses
     * Accepts multipart/form-data for proof file upload.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'marketing_campaign_id' => 'nullable|exists:marketing_campaigns,id',
            'department_budget_id'  => 'nullable|exists:department_budgets,id',
            'platform'              => 'required|string|max:100',
            'description'           => 'required|string|max:500',
            'amount_planned'        => 'required|numeric|min:0',
            'amount_spent'          => 'required|numeric|min:0',
            'currency'              => 'nullable|string|size:3',
            'spend_date'            => 'required|date',
            'social_proof_url'      => 'nullable|url|max:500',
            'proof_file'            => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $filePath = $fileName = $fileType = null;
        if ($request->hasFile('proof_file')) {
            $file     = $request->file('proof_file');
            $filePath = $file->store('campaign-expenses/' . now()->format('Y/m'), 'private');
            $fileName = $file->getClientOriginalName();
            $fileType = $file->getMimeType();
        }

        $expense = CampaignExpense::create([
            ...$validated,
            'submitted_by'   => $request->user()->id,
            'proof_file_path' => $filePath,
            'proof_file_name' => $fileName,
            'proof_file_type' => $fileType,
            'status'         => 'pending',
        ]);

        return response()->json($expense->load('submittedBy:id,first_name,last_name'), 201);
    }

    /**
     * GET /api/v1/campaign-expenses/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $expense = CampaignExpense::with(['submittedBy:id,first_name,last_name,email',
                                          'reviewedBy:id,first_name,last_name',
                                          'campaign:id,name',
                                          'budget:id,title,amount_requested'])
                                   ->findOrFail($id);

        $this->authorizeExpense($request->user(), $expense);

        // Generate a short-lived signed URL for the proof file if present
        $proofUrl = null;
        if ($expense->proof_file_path && Storage::disk('private')->exists($expense->proof_file_path)) {
            $proofUrl = route('campaign-expenses.proof', ['id' => $expense->id]);
        }

        return response()->json([...$expense->toArray(), 'proof_url' => $proofUrl]);
    }

    /**
     * PUT /api/v1/campaign-expenses/{id}
     * Only pending expenses owned by submitter can be updated.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $expense = CampaignExpense::findOrFail($id);

        abort_if($expense->submitted_by !== $request->user()->id, 403);
        abort_if($expense->status !== 'pending', 422, 'Only pending expenses can be edited.');

        $validated = $request->validate([
            'platform'         => 'sometimes|string|max:100',
            'description'      => 'sometimes|string|max:500',
            'amount_planned'   => 'sometimes|numeric|min:0',
            'amount_spent'     => 'sometimes|numeric|min:0',
            'spend_date'       => 'sometimes|date',
            'social_proof_url' => 'nullable|url|max:500',
        ]);

        // Replace proof file if new one uploaded
        if ($request->hasFile('proof_file')) {
            $request->validate(['proof_file' => 'file|mimes:jpg,jpeg,png,pdf|max:10240']);

            if ($expense->proof_file_path) {
                Storage::disk('private')->delete($expense->proof_file_path);
            }

            $file = $request->file('proof_file');
            $validated['proof_file_path'] = $file->store('campaign-expenses/' . now()->format('Y/m'), 'private');
            $validated['proof_file_name'] = $file->getClientOriginalName();
            $validated['proof_file_type'] = $file->getMimeType();
        }

        $expense->update($validated);

        return response()->json($expense);
    }

    /**
     * DELETE /api/v1/campaign-expenses/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $expense = CampaignExpense::findOrFail($id);
        abort_if($expense->submitted_by !== $request->user()->id, 403);
        abort_if($expense->status !== 'pending', 422, 'Only pending expenses can be deleted.');

        if ($expense->proof_file_path) {
            Storage::disk('private')->delete($expense->proof_file_path);
        }
        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }

    /**
     * POST /api/v1/campaign-expenses/{id}/review
     * Finance / marketing manager approves or rejects.
     */
    public function review(Request $request, int $id): JsonResponse
    {
        $user  = $request->user();
        $roles = $user->roles()->pluck('name')->toArray();
        abort_if(
            empty(array_intersect($roles, ['finance', 'cfo', 'vp_marketing', 'admin', 'super_admin'])),
            403,
            'Insufficient role to review expenses.'
        );

        $validated = $request->validate([
            'action' => 'required|in:approved,rejected',
            'notes'  => 'nullable|string|max:1000',
            'reason' => 'required_if:action,rejected|nullable|string|min:10|max:1000',
        ]);

        $expense = CampaignExpense::findOrFail($id);
        abort_if($expense->status !== 'pending', 422, 'Expense is not pending review.');

        $expense->update([
            'status'       => $validated['action'],
            'reviewed_by'  => $user->id,
            'review_notes' => $validated['action'] === 'rejected' ? $validated['reason'] : $validated['notes'],
            'reviewed_at'  => now(),
        ]);

        return response()->json(['message' => 'Expense ' . $validated['action'] . '.', 'expense' => $expense]);
    }

    private function authorizeExpense($user, CampaignExpense $expense): void
    {
        $roles = $user->roles()->pluck('name')->toArray();
        $canSeeAll = !empty(array_intersect($roles, ['finance', 'cfo', 'admin', 'super_admin', 'founder', 'vp_marketing', 'coo', 'ceo', 'president']));
        abort_if(!$canSeeAll && $expense->submitted_by !== $user->id, 403);
    }
}
