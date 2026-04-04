<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Admin\AdminLog;
use App\Models\User;
use App\Notifications\TherapistApproved;
use App\Notifications\TherapistRejected;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminTherapistVerificationController extends BaseController
{
    public function index(Request $request)
    {
        $therapists = User::whereHas('role', function ($query) {
            $query->where('slug', 'therapist');
        })
            ->whereHas('therapistProfile', function ($query) {
                $query->where('is_verified', false);
            })
            ->with('therapistProfile')
            ->paginate(20);

        return $this->sendResponse($therapists, 'Pending therapist verifications retrieved.');
    }

    public function approve(Request $request, User $therapist)
    {
        if (! $therapist->hasRole('therapist')) {
            return $this->sendError('User is not a therapist.');
        }

        if (! $therapist->therapistProfile) {
            return $this->sendError('Therapist profile not found.');
        }

        DB::transaction(function () use ($therapist, $request) {
            $updates = ['is_verified' => true, 'verified_at' => now(), 'status' => 'approved'];
            // Only set founding_started_at on first approval — never overwrite an existing value
            if (is_null($therapist->therapistProfile->founding_started_at)) {
                $updates['founding_started_at'] = now();
            }
            $therapist->therapistProfile->update($updates);

            AdminLog::create([
                'user_id'     => $request->user()->id,
                'action'      => 'approve_therapist',
                'target_type' => User::class,
                'target_id'   => $therapist->id,
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
            ]);

            try {
                $therapist->notify(new TherapistApproved());
            } catch (\Exception $e) {
                Log::error('Failed to send therapist approval notification: '.$e->getMessage());
            }
        });

        return $this->sendResponse($therapist, 'Therapist approved successfully.');
    }

    public function reject(Request $request, User $therapist)
    {
        if (! $therapist->hasRole('therapist')) {
            return $this->sendError('User is not a therapist.');
        }

        if (! $therapist->therapistProfile) {
            return $this->sendError('Therapist profile not found.');
        }

        $request->validate(['reason' => 'required|string|min:10']);

        $reason = $request->input('reason');

        DB::transaction(function () use ($therapist, $request, $reason) {
            $therapist->therapistProfile->update([
                'status'           => 'rejected',
                'is_verified'      => false,
                'rejection_reason' => $reason,
                'rejected_at'      => now(),
            ]);

            AdminLog::create([
                'user_id'     => $request->user()->id,
                'action'      => 'reject_therapist',
                'target_type' => User::class,
                'target_id'   => $therapist->id,
                'details'     => ['reason' => $reason],
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
            ]);

            try {
                $therapist->notify(new TherapistRejected($reason));
            } catch (\Exception $e) {
                Log::error('Failed to send therapist rejection notification: '.$e->getMessage());
            }
        });

        return $this->sendResponse($therapist, 'Therapist rejected and notified.');
    }

    public function viewDocument(Request $request, User $therapist, $type)
    {
        if (! $therapist->therapistProfile) {
            return $this->sendError('Therapist profile not found.', [], 404);
        }

        $path = null;
        if ($type === 'certificate') {
            $path = $therapist->therapistProfile->certificate_url;
        }

        if (! $path || ! \Illuminate\Support\Facades\Storage::disk(config('filesystems.default'))->exists($path)) {
            // \Illuminate\Support\Facades\Log::info("Document not found at path: " . $path);
            // \Illuminate\Support\Facades\Log::info("Disk exists check: " . (\Illuminate\Support\Facades\Storage::disk(config('filesystems.default'))->exists($path) ? 'true' : 'false'));
            return $this->sendError('Document not found.', [], 404);
        }

        return \Illuminate\Support\Facades\Storage::disk(config('filesystems.default'))->download($path);
    }
}
