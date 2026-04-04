<?php

namespace App\Http\Controllers\API\V1\Institutional;

use App\Http\Controllers\API\BaseController;
use App\Models\Institutional\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends BaseController
{
    public function show(Organization $organization)
    {
        if (! $this->canAccess($organization)) {
            return $this->sendError('Unauthorized', [], 403);
        }

        return $this->sendResponse([
            'plan' => $organization->subscription_plan,
            'status' => $organization->status,
            'max_members' => $organization->max_members,
            'current_members' => $organization->members()->count(),
        ], 'Subscription details.');
    }

    public function upgrade(Request $request, Organization $organization)
    {
        if (! $this->canAccess($organization)) {
            return $this->sendError('Unauthorized', [], 403);
        }

        // Logic to upgrade plan (e.g., Stripe checkout)
        // For now, just update the DB if admin
        if ($request->user()->hasRole('admin')) {
            $organization->update([
                'subscription_plan' => $request->plan,
                'max_members' => $request->max_members,
            ]);

            return $this->sendResponse($organization, 'Plan updated.');
        }

        return $this->sendResponse(['url' => 'https://checkout.stripe.com/...'], 'Redirect to payment.');
    }

    /**
     * GET /api/v1/institutional/billing/invoices
     * Returns payment records for the authenticated user's organisation.
     */
    public function invoices(Request $request)
    {
        $user = $request->user();

        $organization = Organization::whereHas('members', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->first();

        if (! $organization) {
            return $this->sendError('No organisation found for this user.', [], 404);
        }

        if (! $this->canAccess($organization)) {
            return $this->sendError('Unauthorized', [], 403);
        }

        // Pull payment records linked to users who are members of this org
        $memberUserIds = $organization->members()->pluck('user_id');

        $invoices = DB::table('payments')
            ->whereIn('user_id', $memberUserIds)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get([
                'id',
                'user_id',
                'amount',
                'currency',
                'status',
                'description',
                'created_at',
                'paid_at',
            ]);

        return $this->sendResponse($invoices, 'Invoices retrieved.');
    }

    protected function canAccess(Organization $organization)
    {
        $user = auth()->user();
        if ($user->hasRole('admin')) {
            return true;
        }

        return $organization->members()
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->exists();
    }
}
