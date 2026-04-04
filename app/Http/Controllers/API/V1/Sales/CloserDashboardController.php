<?php

namespace App\Http\Controllers\API\V1\Sales;

use App\Http\Controllers\API\BaseController;
use App\Models\Deal;
use App\Models\Institutional\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CloserDashboardController extends BaseController
{
    /**
     * Get dashboard overview for the Closer
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // 1. Deals Awaiting Action — scoped to this closer's assigned deals
        $awaitingAction = Deal::whereIn('stage', ['proposal_sent', 'negotiation', 'contract_ready'])
            ->where(function ($q) use ($user) {
                $q->where('closer_id', $user->id)
                  ->orWhere('assigned_to', $user->id);
            })
            ->where('updated_at', '<', now()->subHours(48))
            ->with(['lead', 'owner', 'assignedUser'])
            ->orderBy('updated_at', 'asc')
            ->get();

        // 2. Active Pipeline — only this closer's deals
        $pipeline = Deal::whereIn('stage', ['proposal_sent', 'negotiation', 'contract_ready'])
            ->where(function ($q) use ($user) {
                $q->where('closer_id', $user->id)
                  ->orWhere('assigned_to', $user->id);
            })
            ->with(['lead', 'owner', 'assignedUser'])
            ->orderBy('updated_at', 'desc')
            ->get();

        // 3. My Closed Deals (This Month)
        $closedThisMonth = Deal::where('stage', 'closed_won')
            ->where('closer_id', $user->id)
            ->whereMonth('closed_at', now()->month)
            ->whereYear('closed_at', now()->year)
            ->count();

        $valueThisMonth = Deal::where('stage', 'closed_won')
            ->where('closer_id', $user->id)
            ->whereMonth('closed_at', now()->month)
            ->whereYear('closed_at', now()->year)
            ->sum('value');

        // 4. My Closed Deals (This Quarter)
        $closedThisQuarter = Deal::where('stage', 'closed_won')
            ->where('closer_id', $user->id)
            ->whereBetween('closed_at', [now()->startOfQuarter(), now()->endOfQuarter()])
            ->count();

        $valueThisQuarter = Deal::where('stage', 'closed_won')
            ->where('closer_id', $user->id)
            ->whereBetween('closed_at', [now()->startOfQuarter(), now()->endOfQuarter()])
            ->sum('value');

        // 5. Close Rate (All time)
        $totalWon = Deal::where('stage', 'closed_won')->where('closer_id', $user->id)->count();
        $totalLost = Deal::where('stage', 'closed_lost')->where('closer_id', $user->id)->count();
        $totalClosed = $totalWon + $totalLost;
        $closeRate = $totalClosed > 0 ? round(($totalWon / $totalClosed) * 100, 1) : 0;

        return $this->sendResponse([
            'awaiting_action' => $awaitingAction,
            'pipeline' => $pipeline,
            'performance' => [
                'this_month' => [
                    'count' => $closedThisMonth,
                    'value' => $valueThisMonth,
                ],
                'this_quarter' => [
                    'count' => $closedThisQuarter,
                    'value' => $valueThisQuarter,
                ],
                'close_rate' => $closeRate,
            ],
        ], 'Closer dashboard data retrieved.');
    }

    /**
     * Get the "Ready to Close" queue
     */
    public function readyToClose(Request $request)
    {
        $queue = Deal::where('stage', 'contract_ready')
            ->with(['lead', 'owner', 'assignedUser'])
            ->orderBy('updated_at', 'asc') // Oldest first to prevent staleness
            ->paginate(20);

        return $this->sendResponse($queue, 'Ready to close queue retrieved.');
    }

    /**
     * Mark a deal as Closed-Won
     */
    public function markWon(Request $request, $id)
    {
        $deal = Deal::findOrFail($id);

        if ($deal->stage === 'closed_won') {
            return $this->sendError('Deal is already closed won.');
        }

        DB::beginTransaction();

        try {
            // Update Deal
            $deal->update([
                'stage' => 'closed_won',
                'closed_at' => now(),
                'closer_id' => $request->user()->id,
                'probability' => 100,
            ]);

            // Create Organization (if not exists for this lead's company)
            // Assuming lead has company_name. If not, use deal title.
            $orgName = $deal->lead ? $deal->lead->company_name : $deal->title;
            if (empty($orgName)) {
                $orgName = 'New Organization ('.$deal->title.')';
            }

            // Check if org already exists for this lead to avoid dupes?
            // For now, create new as per requirement "Auto-create the Organization record"
            $org = Organization::create([
                'name' => $orgName,
                'type' => 'corporate', // Default to corporate, could be inferred
                'contact_email' => $deal->lead ? $deal->lead->email : null,
                'relationship_manager_id' => null, // To be assigned by Admin/Head of Sales? Or assign to Closer temporarily?
                // Requirement says: "Notify the Builder (RM) to begin account management"
                // So maybe we assign a default RM or leave null for assignment.
            ]);

            // Log Activity (assuming Activity logging exists, if not just log to Laravel log)
            Log::info("Deal {$deal->id} closed won by {$request->user()->name}. Org {$org->id} created.");

            // Notify RMs (users with role=builder or relationship_manager) about the new account
            $rmUsers = \App\Models\User::whereHas('role', function ($q) {
                $q->whereIn('slug', ['builder', 'relationship_manager']);
            })->get();

            if ($rmUsers->isNotEmpty()) {
                try {
                    $closer = $request->user();
                    \Illuminate\Support\Facades\Notification::send(
                        $rmUsers,
                        new class($org, $closer) extends \Illuminate\Notifications\Notification {
                            public function __construct(
                                private readonly \App\Models\Institutional\Organization $org,
                                private readonly \App\Models\User $closer,
                            ) {}

                            public function via(mixed $notifiable): array { return ['mail']; }

                            public function toMail(mixed $notifiable): \Illuminate\Notifications\Messages\MailMessage
                            {
                                return (new \Illuminate\Notifications\Messages\MailMessage)
                                    ->subject('New Account Ready for Onboarding — '.config('app.name'))
                                    ->greeting("Hi {$notifiable->first_name}!")
                                    ->line('A new corporate account has been created and is ready for relationship management.')
                                    ->line("**Organisation:** {$this->org->name}")
                                    ->line("**Closed by:** {$this->closer->name}")
                                    ->action('View Organisation', url("/admin/organisations/{$this->org->id}"))
                                    ->line('Please begin account setup and schedule the onboarding call.');
                            }
                        }
                    );
                } catch (\Throwable $e) {
                    Log::warning('Failed to send RM notification for closed deal', [
                        'org_id' => $org->id,
                        'error'  => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            return $this->sendResponse([
                'deal' => $deal,
                'organization' => $org,
            ], 'Deal marked as Closed-Won. Organization created.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to close deal', [
                'deal_id'  => $id,
                'user_id'  => $request->user()->id,
                'error'    => $e->getMessage(),
            ]);

            return $this->sendError('Failed to close deal. Please try again.');
        }
    }

    /**
     * Mark a deal as Closed-Lost
     */
    public function markLost(Request $request, $id)
    {
        $request->validate([
            'lost_reason' => 'required|string|in:budget,competitor,timing,no_decision,other',
        ]);

        $deal = Deal::findOrFail($id);

        if ($deal->stage === 'closed_lost') {
            return $this->sendError('Deal is already closed lost.');
        }

        $deal->update([
            'stage' => 'closed_lost',
            'closed_at' => now(),
            'closer_id' => $request->user()->id, // Track who lost it
            'lost_reason' => $request->lost_reason,
            'probability' => 0,
        ]);

        return $this->sendResponse($deal, 'Deal marked as Closed-Lost.');
    }
}
