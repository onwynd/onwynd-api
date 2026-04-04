<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Mail\WaitlistConfirmationEmail;
use App\Mail\WaitlistInviteEmail;
use App\Models\WaitlistSubmission;
use App\Services\Admin\AdminNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class WaitlistController extends BaseController
{
    /**
     * POST /api/v1/public/waitlist
     * Public — no auth required.
     */
    public function submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name'             => 'required|string|max:100',
            'last_name'              => 'required|string|max:100',
            'email'                  => 'required|email|unique:waitlist_submissions,email',
            'role'                   => 'nullable|in:patient,therapist,institution,other',
            'country'                => 'nullable|string|max:100',
            'referral_source'        => 'nullable|string|max:100',
            'message'                => 'nullable|string|max:1000',
            'years_of_experience'    => 'nullable|string|max:50',
            'specialty'              => 'nullable|string|max:255',
            'institution_type'       => 'nullable|in:company,university,hospital,ngo',
            'organization_name'      => 'nullable|string|max:255',
            'company_size'           => 'nullable|string|max:100',
            'student_count'          => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            // Friendly duplicate-email message
            if (isset($validator->errors()->toArray()['email'])) {
                return $this->sendError("You're already on the waitlist — we'll be in touch soon!", [], 409);
            }

            return $this->sendError('Validation failed.', $validator->errors(), 422);
        }

        $submission = WaitlistSubmission::create([
            'first_name'             => $request->first_name,
            'last_name'              => $request->last_name,
            'email'                  => strtolower(trim($request->email)),
            'role'                   => $request->input('role', 'patient'),
            'country'                => $request->country,
            'referral_source'        => $request->referral_source,
            'message'                => $request->message,
            'status'                 => 'pending',
            'years_of_experience'    => $request->years_of_experience,
            'specialty'              => $request->specialty,
            'institution_type'       => $request->institution_type,
            'organization_name'      => $request->organization_name,
            'company_size'           => $request->company_size,
            'student_count'          => $request->student_count,
        ]);

        // Bust the cached count used in admin dashboard
        Cache::forget('waitlist_stats');

        // Notify admins (priority-routed based on role)
        AdminNotificationService::newWaitlistSignup($submission);

        // Send confirmation email — inline by default, queued if MAIL_QUEUE_ENABLED=true
        try {
            $mailable = new WaitlistConfirmationEmail($submission);
            if (config('mail.queue_enabled', false)) {
                Mail::to($submission->email)->queue($mailable);
            } else {
                Mail::to($submission->email)->send($mailable);
            }
            Log::channel('mail')->info('Waitlist confirmation email sent', [
                'email' => $submission->email,
                'name'  => $submission->first_name . ' ' . $submission->last_name,
                'role'  => $submission->role,
                'queued' => config('mail.queue_enabled', false),
            ]);
        } catch (\Throwable $e) {
            Log::channel('mail')->warning('Waitlist confirmation email failed', [
                'email' => $submission->email,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->sendResponse([
            'id'    => $submission->id,
            'email' => $submission->email,
        ], "You're on the list! We'll reach out soon.");
    }

    /**
     * GET /api/v1/admin/waitlist
     * Admin — list all submissions.
     */
    public function index(Request $request)
    {
        $submissions = WaitlistSubmission::query()
            ->when($request->search, fn ($q, $s) => $q->where(function ($q2) use ($s) {
                $q2->where('email', 'like', "%{$s}%")
                   ->orWhere('first_name', 'like', "%{$s}%")
                   ->orWhere('last_name', 'like', "%{$s}%");
            }))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->country, fn ($q, $c) => $q->where('country', $c))
            ->latest()
            ->paginate($request->per_page ?? 25);

        $stats = Cache::remember('waitlist_stats', 120, function () {
            $total    = WaitlistSubmission::count();
            $pending  = WaitlistSubmission::where('status', 'pending')->count();
            $invited  = WaitlistSubmission::where('status', 'invited')->count();
            $declined = WaitlistSubmission::where('status', 'declined')->count();

            $byRole = WaitlistSubmission::selectRaw('role, count(*) as count')
                ->groupBy('role')->pluck('count', 'role');

            $byCountry = WaitlistSubmission::selectRaw('country, count(*) as count')
                ->whereNotNull('country')
                ->groupBy('country')->orderByDesc('count')->limit(10)->pluck('count', 'country');

            $byReferral = WaitlistSubmission::selectRaw('referral_source, count(*) as count')
                ->whereNotNull('referral_source')
                ->groupBy('referral_source')->orderByDesc('count')->limit(10)->pluck('count', 'referral_source');

            $oldestPending = WaitlistSubmission::where('status', 'pending')
                ->orderBy('created_at')->value('created_at');

            return [
                'total'            => $total,
                'pending'          => $pending,
                'invited'          => $invited,
                'declined'         => $declined,
                'conversion_rate'  => $total > 0 ? round($invited / $total * 100, 1) : 0,
                'by_role'          => $byRole,
                'by_country'       => $byCountry,
                'by_referral'      => $byReferral,
                'oldest_pending'   => $oldestPending,
            ];
        });

        return $this->sendResponse([
            'submissions' => $submissions,
            'stats'       => $stats,
        ], 'Waitlist retrieved.');
    }

    /**
     * PATCH /api/v1/admin/waitlist/{id}/invite
     * Mark a submission as invited (triggers invite email).
     */
    public function invite(WaitlistSubmission $waitlist)
    {
        $waitlist->update([
            'status'     => 'invited',
            'invited_at' => now(),
        ]);

        Cache::forget('waitlist_stats');

        try {
            Mail::to($waitlist->email)->queue(new WaitlistInviteEmail($waitlist));
        } catch (\Throwable $e) {
            Log::warning('Waitlist invite email failed', ['email' => $waitlist->email, 'error' => $e->getMessage()]);
        }

        return $this->sendResponse($waitlist->fresh(), 'Invitation sent.');
    }

    /**
     * PATCH /api/v1/admin/waitlist/{id}/status
     * Update status (pending / invited / declined).
     */
    public function updateStatus(Request $request, WaitlistSubmission $waitlist)
    {
        $request->validate(['status' => 'required|in:pending,invited,declined']);

        $waitlist->update(['status' => $request->status]);
        Cache::forget('waitlist_stats');

        return $this->sendResponse($waitlist->fresh(), 'Status updated.');
    }

    /**
     * DELETE /api/v1/admin/waitlist/{id}
     */
    public function destroy(WaitlistSubmission $waitlist)
    {
        $waitlist->delete();
        Cache::forget('waitlist_stats');

        return $this->sendResponse([], 'Removed from waitlist.');
    }

    /**
     * POST /api/v1/admin/waitlist/batch-invite
     * Invite multiple pending submissions at once.
     */
    public function batchInvite(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1|max:100',
            'ids.*' => 'integer|exists:waitlist_submissions,id',
        ]);

        $submissions = WaitlistSubmission::whereIn('id', $request->ids)
            ->where('status', 'pending')
            ->get();

        $invited = 0;
        foreach ($submissions as $submission) {
            $submission->update(['status' => 'invited', 'invited_at' => now()]);
            try {
                Mail::to($submission->email)->queue(new WaitlistInviteEmail($submission));
            } catch (\Throwable $e) {
                Log::warning('Batch invite email failed', ['email' => $submission->email, 'error' => $e->getMessage()]);
            }
            $invited++;
        }

        Cache::forget('waitlist_stats');

        return $this->sendResponse(['invited' => $invited], "{$invited} invitation(s) sent.");
    }

    /**
     * GET /api/v1/admin/waitlist/export
     * Download as CSV.
     */
    public function export()
    {
        $rows = WaitlistSubmission::orderBy('created_at')->get();

        $csv = "ID,First Name,Last Name,Email,Role,Country,Referral Source,Status,Joined At\n";
        foreach ($rows as $row) {
            $csv .= implode(',', [
                $row->id,
                '"'.$row->first_name.'"',
                '"'.$row->last_name.'"',
                $row->email,
                $row->role,
                $row->country ?? '',
                $row->referral_source ?? '',
                $row->status,
                $row->created_at->toDateTimeString(),
            ])."\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="waitlist-'.now()->format('Y-m-d').'.csv"',
        ]);
    }
}
