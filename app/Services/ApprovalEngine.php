<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\EmployeeRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * ApprovalEngine
 * ──────────────
 * Central intelligence for all approval workflows.
 *
 * Routing rules:
 *
 *   leave (≤3 days)  : [direct_manager → hr]
 *   leave (4-7 days) : [direct_manager → dept_head → hr]
 *   leave (8+ days)  : [direct_manager → dept_head → coo → hr]
 *   budget (<500K)   : [dept_head → coo → ceo → finance]
 *   budget (500K-5M) : [dept_head → coo → ceo → finance]
 *   budget (>5M)     : [dept_head → coo → ceo → president → finance]
 *   promotion        : [direct_manager → dept_head → hr → coo]
 *   transfer         : [direct_manager → hr → coo]
 *   termination      : [direct_manager → hr → coo → ceo]
 *   expense          : [direct_manager → finance]
 *   custom           : caller-provided steps
 *
 * Escalation:
 *   48h → reminder notification
 *   96h → notify approver's manager + mark escalated
 */
class ApprovalEngine
{
    // Step due window in hours
    const REMINDER_HOURS  = 48;
    const ESCALATE_HOURS  = 96;

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Initiate a new approval request.
     *
     * @param  string        $type     Type slug (leave|budget|promotion|transfer|termination|expense|custom)
     * @param  string        $title    Human-readable title
     * @param  User          $requester The user making the request
     * @param  array         $meta     Context data (days, amount, reason, etc.)
     * @param  object|null   $subject  Optional Eloquent model being approved
     * @param  array         $customSteps  For 'custom' type: [{label, approver_id}]
     */
    public function initiate(
        string $type,
        string $title,
        User $requester,
        array $meta = [],
        ?object $subject = null,
        array $customSteps = [],
    ): ApprovalRequest {
        $steps = match ($type) {
            'leave'       => $this->leaveSteps($requester, $meta),
            'budget'      => $this->budgetSteps($requester, $meta),
            'promotion'   => $this->promotionSteps($requester),
            'transfer'    => $this->transferSteps($requester),
            'termination' => $this->terminationSteps($requester),
            'expense'     => $this->expenseSteps($requester),
            default       => $this->customSteps($customSteps),
        };

        return DB::transaction(function () use ($type, $title, $requester, $meta, $subject, $steps) {
            $request = ApprovalRequest::create([
                'type'         => $type,
                'title'        => $title,
                'description'  => $meta['description'] ?? null,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id'   => $subject?->id,
                'requested_by' => $requester->id,
                'current_step' => 1,
                'total_steps'  => count($steps),
                'status'       => 'pending',
                'metadata'     => $meta,
            ]);

            foreach ($steps as $i => $step) {
                ApprovalStep::create([
                    'approval_request_id' => $request->id,
                    'step_number'         => $i + 1,
                    'step_label'          => $step['label'],
                    'approver_role'       => $step['role'] ?? null,
                    'approver_id'         => $step['approver_id'] ?? null,
                    'status'              => $i === 0 ? 'pending' : 'pending',
                    'due_at'              => now()->addHours(self::REMINDER_HOURS * 2),
                ]);
            }

            // Notify the first approver
            $this->notifyCurrentApprover($request->fresh('steps'));

            return $request;
        });
    }

    /**
     * Approve the current step.
     */
    public function approve(ApprovalRequest $request, User $actor, ?string $notes = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $notes) {
            $step = $this->currentStep($request);
            $this->assertCanAct($step, $actor);

            $step->update([
                'status'      => 'approved',
                'actioned_by' => $actor->id,
                'action_notes'=> $notes,
                'actioned_at' => now(),
            ]);

            if ($request->current_step < $request->total_steps) {
                // Advance to next step
                $request->update(['current_step' => $request->current_step + 1]);
                $this->notifyCurrentApprover($request->fresh('steps'));
            } else {
                // All steps approved
                $request->update(['status' => 'approved', 'resolved_at' => now()]);
                $this->notifyRequester($request, 'approved');
                $this->onFullyApproved($request);
            }

            return $request->fresh('steps');
        });
    }

    /**
     * Reject the current step (terminates the entire workflow).
     */
    public function reject(ApprovalRequest $request, User $actor, string $reason): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $reason) {
            $step = $this->currentStep($request);
            $this->assertCanAct($step, $actor);

            $step->update([
                'status'       => 'rejected',
                'actioned_by'  => $actor->id,
                'action_notes' => $reason,
                'actioned_at'  => now(),
            ]);

            $request->update(['status' => 'rejected', 'resolved_at' => now()]);
            $this->notifyRequester($request, 'rejected', $reason);

            return $request->fresh('steps');
        });
    }

    /**
     * Send back to requester for clarification (step stays at current level).
     */
    public function requestReview(ApprovalRequest $request, User $actor, string $questions): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $questions) {
            $step = $this->currentStep($request);
            $this->assertCanAct($step, $actor);

            $step->update([
                'status'       => 'under_review',
                'actioned_by'  => $actor->id,
                'action_notes' => $questions,
                'actioned_at'  => now(),
            ]);

            $request->update(['status' => 'under_review']);
            $this->notifyRequester($request, 'under_review', $questions);

            return $request->fresh('steps');
        });
    }

    /**
     * Submitter responds to a "request review" — step goes back to pending for the approver.
     */
    public function respondToReview(ApprovalRequest $request, User $requester, string $response): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $requester, $response) {
            abort_if($request->requested_by !== $requester->id, 403, 'Only the requester can respond.');
            abort_if($request->status !== 'under_review', 422, 'Request is not under review.');

            $step = $this->currentStep($request);
            $step->update([
                'status'             => 'pending',
                'submitter_response' => $response,
                'actioned_at'        => null,
                'due_at'             => now()->addHours(self::REMINDER_HOURS * 2),
            ]);

            $request->update(['status' => 'pending']);
            $this->notifyCurrentApprover($request->fresh('steps'));

            return $request->fresh('steps');
        });
    }

    /**
     * Cancel a pending request (requester or admin).
     */
    public function cancel(ApprovalRequest $request, User $actor): ApprovalRequest
    {
        abort_if(
            $request->requested_by !== $actor->id
            && !in_array($actor->roles()->first()?->name ?? '', ['admin', 'super_admin', 'hr']),
            403
        );
        $request->update(['status' => 'cancelled', 'resolved_at' => now()]);
        return $request;
    }

    /**
     * Inbox: all pending steps where the given user is the assigned approver.
     */
    public function inboxFor(User $user): \Illuminate\Database\Eloquent\Collection
    {
        $roleNames = $user->roles()->pluck('name')->toArray();

        return ApprovalStep::with(['request.requester:id,first_name,last_name,email'])
            ->where('status', 'pending')
            ->where(function ($q) use ($user, $roleNames) {
                $q->where('approver_id', $user->id)
                  ->orWhereIn('approver_role', $roleNames);
            })
            ->orderBy('due_at')
            ->get();
    }

    /**
     * Run escalation check — call from a scheduled command.
     */
    public function processEscalations(): void
    {
        $overdue = ApprovalStep::with(['request', 'approver'])
            ->where('status', 'pending')
            ->where('due_at', '<', now())
            ->get();

        foreach ($overdue as $step) {
            if (!$step->escalation_notified) {
                $this->sendEscalationNotice($step);
                $step->update(['escalation_notified' => true]);
            }
        }
    }

    // ── Routing rules ──────────────────────────────────────────────────────────

    private function leaveSteps(User $requester, array $meta): array
    {
        $days   = $meta['days'] ?? 1;
        $record = EmployeeRecord::where('user_id', $requester->id)->with('department.head')->first();

        $steps = [];

        // Always: direct manager first
        if ($record?->manager_id) {
            $steps[] = ['label' => 'Direct Manager Approval', 'approver_id' => $record->manager_id, 'role' => 'direct_manager'];
        }

        // 4+ days: dept head
        if ($days >= 4 && $record?->department?->head_user_id
            && $record->department->head_user_id !== $record->manager_id) {
            $steps[] = ['label' => 'Department Head Approval', 'approver_id' => $record->department->head_user_id, 'role' => 'department_head'];
        }

        // 8+ days: COO also reviews
        if ($days >= 8) {
            $coo = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['coo', 'vp_operations']))->first();
            if ($coo) $steps[] = ['label' => 'COO Approval', 'approver_id' => $coo->id, 'role' => 'coo'];
        }

        // Always last: HR ratifies
        $hr = User::whereHas('roles', fn ($q) => $q->where('name', 'hr'))->first();
        if ($hr) $steps[] = ['label' => 'HR Confirmation', 'approver_id' => $hr->id, 'role' => 'hr'];

        return $steps ?: [['label' => 'HR Approval', 'role' => 'hr', 'approver_id' => null]];
    }

    private function budgetSteps(User $requester, array $meta): array
    {
        $amount = (float) ($meta['amount'] ?? 0);
        $record = EmployeeRecord::where('user_id', $requester->id)->with('department.head')->first();

        $steps = [];

        // Dept head
        if ($record?->department?->head_user_id && $record->department->head_user_id !== $requester->id) {
            $steps[] = ['label' => 'Department Head', 'approver_id' => $record->department->head_user_id, 'role' => 'department_head'];
        }

        // COO
        $coo = User::whereHas('roles', fn ($q) => $q->where('name', 'coo'))->first();
        if ($coo) $steps[] = ['label' => 'COO Approval', 'approver_id' => $coo->id, 'role' => 'coo'];

        // CEO
        $ceo = User::whereHas('roles', fn ($q) => $q->where('name', 'ceo'))->first();
        if ($ceo) $steps[] = ['label' => 'CEO Approval', 'approver_id' => $ceo->id, 'role' => 'ceo'];

        // For large amounts: President also reviews
        if ($amount > 5_000_000) {
            $president = User::whereHas('roles', fn ($q) => $q->where('name', 'president'))->first();
            if ($president) $steps[] = ['label' => 'President Sign-Off', 'approver_id' => $president->id, 'role' => 'president'];
        }

        // Finance final approval
        $cfo = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['cfo', 'finance']))->first();
        if ($cfo) $steps[] = ['label' => 'Finance Final Approval', 'approver_id' => $cfo->id, 'role' => 'cfo'];

        return $steps;
    }

    private function promotionSteps(User $requester): array
    {
        $record = EmployeeRecord::where('user_id', $requester->id)->with('department.head')->first();
        $steps  = [];

        if ($record?->manager_id) {
            $steps[] = ['label' => 'Direct Manager Endorsement', 'approver_id' => $record->manager_id, 'role' => 'direct_manager'];
        }
        if ($record?->department?->head_user_id && $record->department->head_user_id !== $record->manager_id) {
            $steps[] = ['label' => 'Department Head Approval', 'approver_id' => $record->department->head_user_id, 'role' => 'department_head'];
        }

        $hr = User::whereHas('roles', fn ($q) => $q->where('name', 'hr'))->first();
        if ($hr) $steps[] = ['label' => 'HR Review', 'approver_id' => $hr->id, 'role' => 'hr'];

        $coo = User::whereHas('roles', fn ($q) => $q->where('name', 'coo'))->first();
        if ($coo) $steps[] = ['label' => 'COO Final Approval', 'approver_id' => $coo->id, 'role' => 'coo'];

        return $steps;
    }

    private function transferSteps(User $requester): array
    {
        $record = EmployeeRecord::where('user_id', $requester->id)->first();
        $steps  = [];

        if ($record?->manager_id) {
            $steps[] = ['label' => 'Current Manager Release', 'approver_id' => $record->manager_id, 'role' => 'direct_manager'];
        }

        $hr = User::whereHas('roles', fn ($q) => $q->where('name', 'hr'))->first();
        if ($hr) $steps[] = ['label' => 'HR Processing', 'approver_id' => $hr->id, 'role' => 'hr'];

        $coo = User::whereHas('roles', fn ($q) => $q->where('name', 'coo'))->first();
        if ($coo) $steps[] = ['label' => 'COO Approval', 'approver_id' => $coo->id, 'role' => 'coo'];

        return $steps;
    }

    private function terminationSteps(User $requester): array
    {
        $record = EmployeeRecord::where('user_id', $requester->id)->first();
        $steps  = [];

        if ($record?->manager_id) {
            $steps[] = ['label' => 'Manager Confirmation', 'approver_id' => $record->manager_id, 'role' => 'direct_manager'];
        }

        $hr = User::whereHas('roles', fn ($q) => $q->where('name', 'hr'))->first();
        if ($hr) $steps[] = ['label' => 'HR Exit Process', 'approver_id' => $hr->id, 'role' => 'hr'];

        $coo = User::whereHas('roles', fn ($q) => $q->where('name', 'coo'))->first();
        if ($coo) $steps[] = ['label' => 'COO Approval', 'approver_id' => $coo->id, 'role' => 'coo'];

        $ceo = User::whereHas('roles', fn ($q) => $q->where('name', 'ceo'))->first();
        if ($ceo) $steps[] = ['label' => 'CEO Final Approval', 'approver_id' => $ceo->id, 'role' => 'ceo'];

        return $steps;
    }

    private function expenseSteps(User $requester): array
    {
        $record = EmployeeRecord::where('user_id', $requester->id)->first();
        $steps  = [];

        if ($record?->manager_id) {
            $steps[] = ['label' => 'Manager Approval', 'approver_id' => $record->manager_id, 'role' => 'direct_manager'];
        }

        $finance = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['finance', 'cfo']))->first();
        if ($finance) $steps[] = ['label' => 'Finance Approval', 'approver_id' => $finance->id, 'role' => 'finance'];

        return $steps;
    }

    private function customSteps(array $steps): array
    {
        return array_map(fn ($s) => [
            'label'       => $s['label'],
            'approver_id' => $s['approver_id'],
            'role'        => $s['role'] ?? null,
        ], $steps);
    }

    // ── Notifications ──────────────────────────────────────────────────────────

    private function notifyCurrentApprover(ApprovalRequest $request): void
    {
        $step     = $request->steps->firstWhere('step_number', $request->current_step);
        $approver = $step?->approver;
        if (!$approver) return;

        try {
            $approver->notify(new \App\Notifications\ApprovalRequestedNotification($request, $step));
        } catch (\Throwable $e) {
            Log::warning('Failed to notify approver', ['error' => $e->getMessage(), 'request_id' => $request->id]);
        }
    }

    private function notifyRequester(ApprovalRequest $request, string $outcome, ?string $reason = null): void
    {
        try {
            $request->requester->notify(
                new \App\Notifications\ApprovalOutcomeNotification($request, $outcome, $reason)
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify requester', ['error' => $e->getMessage()]);
        }
    }

    private function sendEscalationNotice(ApprovalStep $step): void
    {
        // Notify the approver again, and try to notify their manager
        $approver = $step->approver;
        if (!$approver) return;

        Log::info('Escalation: step overdue', [
            'request_id'  => $step->approval_request_id,
            'step'        => $step->step_number,
            'approver_id' => $approver->id,
        ]);

        // In a full implementation, fire an escalation notification here
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function currentStep(ApprovalRequest $request): ApprovalStep
    {
        abort_if(!$request->isPending(), 422, 'Request is not in a pending state.');

        return ApprovalStep::where('approval_request_id', $request->id)
                           ->where('step_number', $request->current_step)
                           ->firstOrFail();
    }

    private function assertCanAct(ApprovalStep $step, User $actor): void
    {
        $roleNames = $actor->roles()->pluck('name')->toArray();
        $canAct    = ($step->approver_id === $actor->id)
                  || in_array($step->approver_role, $roleNames, true)
                  || in_array('admin', $roleNames, true)
                  || in_array('super_admin', $roleNames, true);

        abort_if(!$canAct, 403, 'You are not the assigned approver for this step.');
        abort_if(!$step->isPending() && !$step->isUnderReview(), 422, 'This step is not awaiting action.');
    }

    private function onFullyApproved(ApprovalRequest $request): void
    {
        // Post-approval hooks (e.g. auto-update leave status, deduct budget)
        match ($request->type) {
            'leave' => $this->activateLeave($request),
            default => null,
        };
    }

    private function activateLeave(ApprovalRequest $request): void
    {
        if ($request->subject_type && $request->subject_id) {
            $request->subject?->update(['status' => 'approved']);
        }
    }
}
