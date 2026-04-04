<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Admin\AdminLog;
use App\Models\TherapistLocationMismatch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TherapistController extends BaseController
{
    /**
     * Display a listing of therapists with optional filters.
     *
     * Params:
     *   ?search=                           name or email search
     *   ?status=active|inactive            account active state
     *   ?verification_status=pending|approved|rejected|no_profile
     *   ?page=
     */
    public function index(Request $request)
    {
        $base = fn () => User::whereHas('role', fn ($q) => $q->where('slug', 'therapist'));

        // Stat counts for the UI cards
        $counts = [
            'total' => $base()->count(),
            'active' => $base()->where('is_active', true)->count(),
            'inactive' => $base()->where('is_active', false)->count(),
            'pending' => $base()->whereHas('therapistProfile', fn ($q) => $q->where('status', 'pending'))->count(),
            'verified' => $base()->whereHas('therapistProfile', fn ($q) => $q->where('status', 'approved'))->count(),
            'rejected' => $base()->whereHas('therapistProfile', fn ($q) => $q->where('status', 'rejected'))->count(),
            'no_profile' => $base()->whereDoesntHave('therapistProfile')->count(),
        ];

        $query = $base()->with(['therapistProfile']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'active' => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                default => null,
            };
        }

        if ($request->filled('verification_status')) {
            $vs = $request->verification_status;
            if ($vs === 'no_profile') {
                $query->whereDoesntHave('therapistProfile');
            } else {
                $query->whereHas('therapistProfile', fn ($q) => $q->where('status', $vs));
            }
        }

        $therapists = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->sendResponse([
            'therapists' => $therapists,
            'counts' => $counts,
        ], 'Therapists retrieved successfully.');
    }

    public function show(User $therapist)
    {
        if (! $therapist->hasRole('therapist')) {
            return $this->sendError('User is not a therapist.');
        }

        $therapist->load(['therapistProfile', 'roles']);

        return $this->sendResponse($therapist, 'Therapist details retrieved successfully.');
    }

    public function deactivate(Request $request, User $therapist)
    {
        if (! $therapist->hasRole('therapist')) {
            return $this->sendError('User is not a therapist.');
        }

        $therapist->update(['is_active' => false]);

        AdminLog::create([
            'user_id' => $request->user()->id,
            'action' => 'deactivate_therapist',
            'target_type' => User::class,
            'target_id' => $therapist->id,
            'details' => ['reason' => $request->reason ?? 'Admin deactivation'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse($therapist, 'Therapist deactivated successfully.');
    }

    public function activate(Request $request, User $therapist)
    {
        if (! $therapist->hasRole('therapist')) {
            return $this->sendError('User is not a therapist.');
        }

        $therapist->update(['is_active' => true]);

        AdminLog::create([
            'user_id' => $request->user()->id,
            'action' => 'activate_therapist',
            'target_type' => User::class,
            'target_id' => $therapist->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse($therapist, 'Therapist activated successfully.');
    }

    /**
     * Retrieve location-mismatch flags grouped by therapist for admin review.
     *
     * GET /api/v1/admin/therapists/location-flags
     */
    public function locationFlags(Request $request): \Illuminate\Http\JsonResponse
    {
        $flags = TherapistLocationMismatch::with(['therapist.user:id,name,email'])
            ->where('resolved', false)
            ->selectRaw('
                therapist_id,
                COUNT(*) as mismatch_count,
                MAX(detected_at) as last_detected_at,
                MAX(ip_address) as last_ip,
                MIN(stored_country) as stored_country,
                MIN(detected_country) as detected_country
            ')
            ->groupBy('therapist_id')
            ->orderByDesc('mismatch_count')
            ->paginate(25);

        return $this->sendResponse($flags, 'Location flags retrieved.');
    }

    /**
     * Resolve a therapist's open location-mismatch flag.
     *
     * POST /api/v1/admin/therapists/{therapist}/resolve-location-flag
     *
     * Body: { "action": "dismiss"|"reverify"|"suspend"|"update_country", "note"?: string, "new_country"?: string }
     */
    public function resolveLocationFlag(Request $request, User $therapist)
    {
        if (! $therapist->hasRole('therapist')) {
            return $this->sendError('User is not a therapist.');
        }

        $validated = $request->validate([
            'action'      => 'required|in:dismiss,reverify,suspend,update_country',
            'note'        => 'nullable|string|max:500',
            'new_country' => 'required_if:action,update_country|nullable|string|max:10',
        ]);

        $profile = $therapist->therapistProfile;

        if (! $profile) {
            return $this->sendError('Therapist profile not found.');
        }

        $action = $validated['action'];

        // Mark all unresolved mismatches for this therapist as resolved
        TherapistLocationMismatch::where('therapist_id', $profile->id)
            ->where('resolved', false)
            ->update([
                'resolved'    => true,
                'resolved_at' => now(),
                'resolved_by' => Auth::id(),
            ]);

        // Apply action-specific mutations
        if ($action === 'suspend') {
            $profile->update([
                'status'          => 'suspended',
                'account_flagged' => true,
                'flag_reason'     => 'location_mismatch',
                'flag_note'       => $validated['note'] ?? null,
                'flagged_at'      => now(),
            ]);
            try {
                $therapist->notify(new \App\Notifications\TherapistReverificationRequired());
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send suspend notification: '.$e->getMessage());
            }
        } elseif ($action === 'reverify') {
            $profile->update([
                'account_flagged' => false,
                'flag_reason'     => null,
                'flag_note'       => null,
            ]);
            try {
                $therapist->notify(new \App\Notifications\TherapistReverificationRequired());
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send re-verify notification: '.$e->getMessage());
            }
        } elseif ($action === 'update_country') {
            $profile->update([
                'country_of_operation' => $validated['new_country'],
                'account_flagged'      => false,
                'flag_reason'          => null,
                'flag_note'            => null,
            ]);
        } else {
            // dismiss — just clear the flag
            $profile->update([
                'account_flagged' => false,
                'flag_reason'     => null,
                'flag_note'       => null,
            ]);
        }

        AdminLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'resolve_location_flag',
            'target_type' => User::class,
            'target_id'   => $therapist->id,
            'details'     => [
                'resolution_action' => $action,
                'note'              => $validated['note'] ?? null,
                'new_country'       => $validated['new_country'] ?? null,
            ],
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return $this->sendResponse(['action' => $action], 'Flag resolved.');
    }
}
