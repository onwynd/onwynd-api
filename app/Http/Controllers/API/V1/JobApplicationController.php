<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Mail\EmployeeWelcomeEmail;
use App\Mail\JobApplicationConfirmation;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class JobApplicationController extends Controller
{
    /**
     * POST /api/v1/careers/jobs/{slug}/apply
     * Public: submit a job application.
     */
    public function apply(Request $request, string $slug): JsonResponse
    {
        $job = JobPosting::active()->where('slug', $slug)->first();

        if (! $job) {
            return response()->json(['status' => 'error', 'message' => 'Job not found or no longer accepting applications.'], 404);
        }

        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:30',
            'location' => 'nullable|string|max:255',
            'cover_letter' => 'nullable|string|max:5000',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'linkedin_url' => 'nullable|url|max:500',
            'portfolio_url' => 'nullable|url|max:500',
            'experience' => 'nullable|array',
        ]);

        // Block duplicate applications
        $exists = JobApplication::where('job_posting_id', $job->id)
            ->where('email', $data['email'])
            ->whereNotIn('status', ['withdrawn', 'rejected'])
            ->exists();

        if ($exists) {
            return response()->json(['status' => 'error', 'message' => 'You have already applied for this position.'], 409);
        }

        $resumeUrl = null;
        if ($request->hasFile('resume')) {
            $file = $request->file('resume');
            $resumeUrl = $file->store("applications/{$job->id}/resumes", 'public');
        }

        $application = JobApplication::create([
            'job_posting_id' => $job->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'location' => $data['location'] ?? null,
            'cover_letter' => $data['cover_letter'] ?? null,
            'resume_url' => $resumeUrl,
            'linkedin_url' => $data['linkedin_url'] ?? null,
            'portfolio_url' => $data['portfolio_url'] ?? null,
            'experience' => $data['experience'] ?? null,
        ]);

        Log::info('Job application submitted', ['application_id' => $application->id, 'job' => $job->slug]);

        // Send confirmation email to applicant
        try {
            Mail::to($application->email)->send(new JobApplicationConfirmation($application, $job));
            Log::info('Job application confirmation email sent', ['application_id' => $application->id, 'email' => $application->email]);
        } catch (\Exception $e) {
            Log::error('Failed to send job application confirmation email', [
                'application_id' => $application->id,
                'email' => $application->email,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Application submitted successfully. We will be in touch!',
            'data' => ['uuid' => $application->uuid],
        ], 201);
    }

    // ─── Admin / HR endpoints ──────────────────────────────────────────────

    /**
     * GET /api/v1/admin/careers/applications
     * List all applications with filters; admin & HR only.
     */
    public function index(Request $request): JsonResponse
    {
        $query = JobApplication::with(['jobPosting:id,title,slug,department', 'reviewer:id,first_name,last_name'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('job_id')) {
            $query->where('job_posting_id', $request->job_id);
        }
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($sq) use ($q) {
                $sq->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $perPage = min($request->input('per_page', 20), 100);
        $applications = $query->paginate($perPage);

        return response()->json(['status' => 'success', 'data' => $applications]);
    }

    /**
     * GET /api/v1/admin/careers/applications/recent
     * Returns the 10 most recent applications for the admin/HR dashboard widget.
     */
    public function recent(): JsonResponse
    {
        $applications = JobApplication::with('jobPosting:id,title,slug,department')
            ->latest()
            ->limit(10)
            ->get(['id', 'uuid', 'job_posting_id', 'first_name', 'last_name', 'email', 'status', 'created_at']);

        return response()->json(['status' => 'success', 'data' => $applications]);
    }

    /**
     * GET /api/v1/admin/careers/applications/{uuid}
     * Show a single application.
     */
    public function show(string $uuid): JsonResponse
    {
        $application = JobApplication::where('uuid', $uuid)
            ->with(['jobPosting', 'reviewer:id,first_name,last_name'])
            ->firstOrFail();

        return response()->json(['status' => 'success', 'data' => $application]);
    }

    /**
     * PATCH /api/v1/admin/careers/applications/{uuid}
     * Update status / HR notes.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $application = JobApplication::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'status' => 'sometimes|in:pending,reviewing,shortlisted,interviewed,offered,hired,rejected,withdrawn',
            'hr_notes' => 'sometimes|nullable|string|max:5000',
        ]);

        if (isset($data['status']) && $data['status'] !== $application->status) {
            $data['reviewed_by'] = Auth::id();
            $data['reviewed_at'] = now();
        }

        $application->update($data);

        return response()->json(['status' => 'success', 'message' => 'Application updated.', 'data' => $application]);
    }

    /**
     * POST /api/v1/admin/careers/applications/{uuid}/onboard
     *
     * Create a dashboard account for an accepted/hired applicant.
     * Sends a password-reset (invite) email so they can set their own password.
     */
    public function onboard(Request $request, string $uuid): JsonResponse
    {
        $application = JobApplication::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'role'        => 'required|string',
            'department'  => 'nullable|string|max:80',
            'send_invite' => 'nullable|boolean',
        ]);

        // Must be accepted / hired
        if (! in_array($application->status, ['accepted', 'hired', 'offered'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Applicant must be accepted before onboarding.',
            ], 422);
        }

        // Check if user already exists
        if (User::where('email', $application->email)->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'A user account with this email already exists.',
            ], 409);
        }

        // Resolve role model
        $role = Role::where('slug', $data['role'])->first();

        $user = User::create([
            'first_name' => $application->first_name,
            'last_name'  => $application->last_name,
            'email'      => $application->email,
            'phone'      => $application->phone,
            'password'   => Hash::make(Str::random(24)), // will be reset via invite
            'role_id'    => $role?->id,
            'department' => $data['department'] ?? $application->jobPosting?->department,
            'is_active'  => true,
        ]);

        // Mark application as hired
        $application->update(['status' => 'hired', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

        Log::info('Applicant onboarded', ['user_id' => $user->id, 'application_uuid' => $uuid]);

        // Send invite (password reset) email + branded employee welcome email
        if ($data['send_invite'] ?? true) {
            try {
                Password::sendResetLink(['email' => $user->email]);
            } catch (\Exception $e) {
                Log::warning('Onboard invite email failed', ['error' => $e->getMessage()]);
            }

            try {
                $loginUrl = rtrim(config('frontend.dashboard_url'), '/') . '/login';

                Mail::to($user->email)->queue(new EmployeeWelcomeEmail(
                    name:     trim("{$user->first_name} {$user->last_name}"),
                    role:     $data['role'],
                    loginUrl: $loginUrl,
                ));
            } catch (\Exception $e) {
                Log::warning('Employee welcome email failed', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'User account created. An invite email has been sent.',
            'data'    => [
                'user_id'   => $user->id,
                'email'     => $user->email,
                'role'      => $data['role'],
                'full_name' => trim("{$user->first_name} {$user->last_name}"),
            ],
        ], 201);
    }

    /**
     * DELETE /api/v1/admin/careers/applications/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $application = JobApplication::where('uuid', $uuid)->firstOrFail();

        if ($application->resume_url) {
            Storage::disk('public')->delete($application->resume_url);
        }

        $application->delete();

        return response()->json(['status' => 'success', 'message' => 'Application deleted.']);
    }
}
