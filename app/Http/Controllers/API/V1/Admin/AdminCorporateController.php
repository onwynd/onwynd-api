<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Mail\Corporate\PilotActivatedEmail;
use App\Mail\Corporate\PilotExpiredEmail;
use App\Mail\Corporate\PilotMidpointEmail;
use App\Mail\Corporate\PilotPreRenewalEmail;
use App\Models\InstitutionalContract;
use App\Models\Institutional\Organization;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminCorporateController extends BaseController
{
    /**
     * GET /api/v1/admin/corporates
     * List all corporate accounts with pilot/contract status.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Organization::where('type', 'corporate')
            ->orWhere('org_type', 'corporate');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orgs = $query->with('members')->paginate((int) $request->get('per_page', 20));

        // Enrich each org with its contract data
        $orgs->getCollection()->transform(function (Organization $org) {
            $contract = InstitutionalContract::where('institution_user_id', function ($sub) use ($org) {
                $sub->select('user_id')
                    ->from('organization_members')
                    ->where('organization_id', $org->id)
                    ->where('role', 'admin')
                    ->limit(1);
            })->latest()->first();

            $pilotStatus = $this->resolvePilotStatus($org, $contract);

            return [
                'id'                    => $org->id,
                'name'                  => $org->name,
                'contact_email'         => $org->contact_email,
                'plan_tier'             => $org->subscription_plan,
                'status'                => $org->status,
                'pilot_status'          => $pilotStatus,
                'contracted_seats'      => $org->contracted_seats ?? $org->max_members,
                'current_seats'         => $org->current_seats ?? $org->members->count(),
                'contract'              => $contract ? [
                    'id'                     => $contract->id,
                    'contract_type'          => $contract->contract_type,
                    'start_date'             => $contract->start_date?->toDateString(),
                    'end_date'               => $contract->end_date?->toDateString(),
                    'status'                 => $contract->status,
                    'employee_count_limit'   => $contract->employee_count_limit,
                    'total_sessions_quota'   => $contract->total_sessions_quota,
                    'sessions_used'          => $contract->sessions_used,
                    'contract_value'         => $contract->contract_value,
                    'midpoint_notified_at'   => $contract->midpoint_notified_at?->toIso8601String(),
                    'pre_renewal_notified_at'=> $contract->pre_renewal_notified_at?->toIso8601String(),
                    'expiry_notified_at'     => $contract->expiry_notified_at?->toIso8601String(),
                    'activated_notified_at'  => $contract->created_at?->toIso8601String(),
                ] : null,
            ];
        });

        return $this->sendResponse($orgs, 'Corporate accounts retrieved successfully.');
    }

    /**
     * POST /api/v1/admin/corporates/{corporate}/send-lifecycle-email
     * Manually trigger a lifecycle email for a corporate account.
     * Body: { "email_type": "activated|midpoint|pre_renewal|expired" }
     */
    public function sendLifecycleEmail(Request $request, Organization $corporate): JsonResponse
    {
        if ($corporate->type !== 'corporate' && $corporate->org_type !== 'corporate') {
            return $this->sendError('Organization is not a corporate entity.', [], 404);
        }

        $validated = $request->validate([
            'email_type' => 'required|in:activated,midpoint,pre_renewal,expired',
        ]);

        $contract = InstitutionalContract::where('institution_user_id', function ($sub) use ($corporate) {
            $sub->select('user_id')
                ->from('organization_members')
                ->where('organization_id', $corporate->id)
                ->where('role', 'admin')
                ->limit(1);
        })->latest()->first();

        if (! $contract) {
            return $this->sendError('No contract found for this corporate account.', [], 422);
        }

        $hrEmail   = $corporate->contact_email;
        $hrName    = 'HR Director';

        if (! $hrEmail) {
            return $this->sendError('No contact email set on this corporate account.', [], 422);
        }

        $pilotStart = $contract->start_date ? Carbon::parse($contract->start_date) : now();
        $pilotEnd   = $contract->end_date   ? Carbon::parse($contract->end_date)   : now()->addMonths(3);
        $sessionsUsed      = (int) ($contract->sessions_used ?? 0);
        $sessionsTotal     = (int) ($contract->total_sessions_quota ?? 0);
        $sessionsRemaining = max(0, $sessionsTotal - $sessionsUsed);
        $usageRatePct      = $sessionsTotal > 0 ? round(($sessionsUsed / $sessionsTotal) * 100, 1) : 0.0;
        $renewalUrl        = config('app.frontend_url', 'https://app.onwynd.com') . '/corporate/renew';

        switch ($validated['email_type']) {
            case 'activated':
                Mail::to($hrEmail)->queue(new PilotActivatedEmail(
                    orgName:      $corporate->name,
                    hrName:       $hrName,
                    pilotStart:   $pilotStart,
                    pilotEnd:     $pilotEnd,
                    sessionQuota: $sessionsTotal,
                    currency:     'NGN',
                    sessionFee:   (float) ($contract->contract_value ?? 0),
                    bookingFee:   0.0,
                ));
                break;

            case 'midpoint':
                Mail::to($hrEmail)->queue(new PilotMidpointEmail(
                    orgName:           $corporate->name,
                    hrName:            $hrName,
                    pilotEnd:          $pilotEnd,
                    sessionsUsed:      $sessionsUsed,
                    sessionsRemaining: $sessionsRemaining,
                    sessionsTotal:     $sessionsTotal,
                    usageRatePct:      $usageRatePct,
                ));
                $contract->update(['midpoint_notified_at' => now()]);
                break;

            case 'pre_renewal':
                Mail::to($hrEmail)->queue(new PilotPreRenewalEmail(
                    orgName:       $corporate->name,
                    hrName:        $hrName,
                    pilotEnd:      $pilotEnd,
                    sessionsUsed:  $sessionsUsed,
                    sessionsTotal: $sessionsTotal,
                    renewalUrl:    $renewalUrl,
                ));
                $contract->update(['pre_renewal_notified_at' => now()]);
                break;

            case 'expired':
                Mail::to($hrEmail)->queue(new PilotExpiredEmail(
                    orgName:      $corporate->name,
                    hrName:       $hrName,
                    expiryDate:   $pilotEnd,
                    sessionsUsed: $sessionsUsed,
                    sessionsTotal:$sessionsTotal,
                ));
                $contract->update(['expiry_notified_at' => now()]);
                break;
        }

        Log::info('Admin manually triggered corporate lifecycle email', [
            'admin_id'    => auth()->id(),
            'org_id'      => $corporate->id,
            'contract_id' => $contract->id,
            'email_type'  => $validated['email_type'],
            'recipient'   => $hrEmail,
        ]);

        return $this->sendResponse([
            'email_type' => $validated['email_type'],
            'sent_to'    => $hrEmail,
        ], "Lifecycle email '{$validated['email_type']}' queued successfully.");
    }

    /**
     * POST /api/v1/admin/corporates/{corporate}/extend-pilot
     * Extend the pilot end_date by N days.
     * Body: { "days": 14 }
     */
    public function extendPilot(Request $request, Organization $corporate): JsonResponse
    {
        if ($corporate->type !== 'corporate' && $corporate->org_type !== 'corporate') {
            return $this->sendError('Organization is not a corporate entity.', [], 404);
        }

        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:90',
        ]);

        $contract = InstitutionalContract::where('institution_user_id', function ($sub) use ($corporate) {
            $sub->select('user_id')
                ->from('organization_members')
                ->where('organization_id', $corporate->id)
                ->where('role', 'admin')
                ->limit(1);
        })->latest()->first();

        if (! $contract) {
            return $this->sendError('No contract found for this corporate account.', [], 422);
        }

        $currentEnd   = $contract->end_date ? Carbon::parse($contract->end_date) : now();
        $newEnd       = $currentEnd->addDays($validated['days']);
        $contract->update(['end_date' => $newEnd->toDateString()]);

        Log::info('Admin extended corporate pilot', [
            'admin_id'    => auth()->id(),
            'org_id'      => $corporate->id,
            'contract_id' => $contract->id,
            'days_added'  => $validated['days'],
            'new_end_date'=> $newEnd->toDateString(),
        ]);

        return $this->sendResponse([
            'contract_id'  => $contract->id,
            'days_added'   => $validated['days'],
            'new_end_date' => $newEnd->toDateString(),
        ], "Pilot extended by {$validated['days']} day(s). New end date: {$newEnd->toDateString()}.");
    }

    /**
     * POST /api/v1/admin/corporates/{corporate}/convert-to-paid
     * Convert a pilot contract to a paid plan.
     * Body: { "plan_tier": "starter|growth|enterprise", "billing_cycle": "monthly|annual" }
     */
    public function convertToPaid(Request $request, Organization $corporate): JsonResponse
    {
        if ($corporate->type !== 'corporate' && $corporate->org_type !== 'corporate') {
            return $this->sendError('Organization is not a corporate entity.', [], 404);
        }

        $validated = $request->validate([
            'plan_tier'     => 'required|in:starter,growth,enterprise',
            'billing_cycle' => 'required|in:monthly,annual',
        ]);

        $contract = InstitutionalContract::where('institution_user_id', function ($sub) use ($corporate) {
            $sub->select('user_id')
                ->from('organization_members')
                ->where('organization_id', $corporate->id)
                ->where('role', 'admin')
                ->limit(1);
        })->latest()->first();

        if (! $contract) {
            return $this->sendError('No contract found for this corporate account.', [], 422);
        }

        $contract->update([
            'status'        => 'paid',
            'contract_type' => $validated['billing_cycle'],
        ]);

        $corporate->update([
            'subscription_plan' => $validated['plan_tier'],
            'status'            => 'active',
        ]);

        // Send PilotActivatedEmail as conversion confirmation if not already sent previously
        $hrEmail = $corporate->contact_email;
        if ($hrEmail) {
            $pilotStart   = $contract->start_date ? Carbon::parse($contract->start_date) : now();
            $pilotEnd     = $contract->end_date   ? Carbon::parse($contract->end_date)   : now()->addYear();
            $sessionsTotal = (int) ($contract->total_sessions_quota ?? 0);

            Mail::to($hrEmail)->queue(new PilotActivatedEmail(
                orgName:      $corporate->name,
                hrName:       'HR Director',
                pilotStart:   $pilotStart,
                pilotEnd:     $pilotEnd,
                sessionQuota: $sessionsTotal,
                currency:     'NGN',
                sessionFee:   (float) ($contract->contract_value ?? 0),
                bookingFee:   0.0,
            ));
        }

        Log::info('Admin converted corporate pilot to paid plan', [
            'admin_id'      => auth()->id(),
            'org_id'        => $corporate->id,
            'contract_id'   => $contract->id,
            'plan_tier'     => $validated['plan_tier'],
            'billing_cycle' => $validated['billing_cycle'],
        ]);

        return $this->sendResponse([
            'contract_id'   => $contract->id,
            'status'        => 'paid',
            'plan_tier'     => $validated['plan_tier'],
            'billing_cycle' => $validated['billing_cycle'],
        ], "Corporate account converted to paid plan '{$validated['plan_tier']}' ({$validated['billing_cycle']}).");
    }

    /**
     * Determine the pilot status of a corporate account based on contract dates and status.
     */
    private function resolvePilotStatus(Organization $org, ?InstitutionalContract $contract): string
    {
        if (! $contract) {
            return 'pending';
        }

        if ($contract->status === 'paid') {
            return 'paid';
        }

        $now = now();

        if ($contract->end_date && Carbon::parse($contract->end_date)->lt($now)) {
            return 'expired';
        }

        if ($contract->status === 'active') {
            return 'active';
        }

        return 'pending';
    }
}
