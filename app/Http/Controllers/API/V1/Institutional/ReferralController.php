<?php

namespace App\Http\Controllers\API\V1\Institutional;

use App\Http\Controllers\API\BaseController;
use App\Models\Ambassador;
use App\Models\Referral;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ReferralController extends BaseController
{
    /**
     * Display a listing of referrals for the institution.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Find the ambassador profile for the current user
        $ambassador = Ambassador::where('user_id', $user->id)->first();

        if (! $ambassador) {
            return $this->sendResponse([], 'No ambassador profile found for this user.');
        }

        $query = Referral::where('ambassador_id', $ambassador->id)
            ->with(['referredUser.activeSubscription.plan', 'referredUser.profile']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('referredUser', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $referrals = $query->latest()->paginate(10);

        // Transform the data to match frontend expectations
        $transformed = $referrals->getCollection()->map(function ($referral) {
            $patient = $referral->referredUser;
            // Prefer referral plan, fallback to active subscription plan
            $planName = $referral->plan ? $referral->plan->name : ($patient && $patient->activeSubscription && $patient->activeSubscription->plan
                ? $patient->activeSubscription->plan->name
                : 'No Plan');

            return [
                'id' => $referral->id,
                'uuid' => $referral->uuid,
                'ambassador_id' => $referral->ambassador_id,
                'referred_user_id' => $referral->referred_user_id,
                'status' => $referral->status, // 'pending', 'completed', 'cancelled'
                'amount' => $referral->amount,
                'created_at' => $referral->created_at->format('Y-m-d'),
                'date' => $referral->created_at->format('Y-m-d'), // For frontend compatibility
                'patientName' => $patient ? $patient->name : 'Unknown User',
                'program' => $planName,
                'doctor' => 'Unassigned', // Placeholder as we don't have direct doctor link yet
                'doctorName' => 'Unassigned', // For frontend compatibility
                'avatar' => $patient && $patient->profile_photo ? $patient->profile_photo : null,
            ];
        });

        $referrals->setCollection($transformed);

        return $this->sendResponse($referrals, 'Referrals retrieved successfully.');
    }

    /**
     * Create a new referral.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'program' => 'nullable|string', // Can be plan ID or name
            'plan_id' => 'nullable|exists:subscription_plans,id',
            'doctor_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $user = Auth::user();
        $ambassador = Ambassador::where('user_id', $user->id)->first();

        if (! $ambassador) {
            return $this->sendError('Ambassador profile not found.', [], 404);
        }

        // Determine Plan ID
        $planId = $request->plan_id;
        if (! $planId && $request->program) {
            // Try to find plan by name or slug if program string is provided
            $plan = SubscriptionPlan::where('name', $request->program)
                ->orWhere('slug', $request->program)
                ->first();
            if ($plan) {
                $planId = $plan->id;
            }
        }

        // Find or create the referred user
        $referredUser = \App\Models\User::where('email', $request->email)->first();

        if (! $referredUser) {
            // Create a new user
            $referredUser = \App\Models\User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => bcrypt(\Illuminate\Support\Str::random(16)), // Temporary password
                'role_id' => \App\Models\Role::where('slug', 'patient')->first()->id ?? 1, // Default to patient or fallback
            ]);

            // In a real app, send invitation email here
        }

        $referral = Referral::create([
            'ambassador_id' => $ambassador->id,
            'referred_user_id' => $referredUser->id,
            'plan_id' => $planId,
            'status' => 'pending',
            'amount' => 0, // Will be calculated based on program/rules
        ]);

        return $this->sendResponse($referral, 'Referral created successfully.');
    }

    /**
     * Display the specified referral.
     */
    public function show($id)
    {
        $referral = Referral::with(['referredUser.profile', 'referredUser.activeSubscription.plan'])->find($id);

        if (is_null($referral)) {
            return $this->sendError('Referral not found.');
        }

        return $this->sendResponse($referral, 'Referral retrieved successfully.');
    }

    /**
     * Update the specified referral in storage.
     */
    public function update(Request $request, $id)
    {
        $referral = Referral::find($id);

        if (is_null($referral)) {
            return $this->sendError('Referral not found.');
        }

        // Only allow updating status for now
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|required|in:pending,active,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        if ($request->has('status')) {
            $referral->status = $request->status;
        }

        $referral->save();

        return $this->sendResponse($referral, 'Referral updated successfully.');
    }

    /**
     * Remove the specified referral from storage.
     */
    public function destroy($id)
    {
        $referral = Referral::find($id);

        if (is_null($referral)) {
            return $this->sendError('Referral not found.');
        }

        $referral->delete();

        return $this->sendResponse([], 'Referral deleted successfully.');
    }
}
