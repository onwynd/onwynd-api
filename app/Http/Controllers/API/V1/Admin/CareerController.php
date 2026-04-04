<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CareerController extends BaseController
{
    /**
     * GET /api/v1/admin/careers
     * List all job postings (active and inactive).
     */
    public function index(Request $request)
    {
        $query = JobPosting::orderBy('created_at', 'desc');

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('department', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")
            );
        }

        $jobs = $query->paginate($request->input('per_page', 15));

        return $this->sendResponse($jobs, 'Job postings retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/careers/{id}
     */
    public function show($id)
    {
        $job = JobPosting::where('id', $id)->orWhere('uuid', $id)->first();

        if (! $job) {
            return $this->sendError('Job posting not found.', [], 404);
        }

        return $this->sendResponse($job, 'Job posting retrieved successfully.');
    }

    /**
     * POST /api/v1/admin/careers
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'department' => 'required|string|max:100',
            'location' => 'required|string|max:255',
            'type' => 'required|in:full-time,part-time,contract,internship,remote',
            'salary_range' => 'nullable|string|max:100',
            'experience_level' => 'nullable|in:entry,mid,senior,lead,executive',
            'description' => 'required|string',
            'responsibilities' => 'nullable|array',
            'qualifications' => 'nullable|array',
            'benefits' => 'nullable|array',
            'is_active' => 'boolean',
            'status' => 'nullable|in:open,filled,closed',
            'posted_at' => 'nullable|date',
            'application_deadline' => 'nullable|date',
            'max_applicants' => 'nullable|integer|min:1|max:10000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $status = $request->input('status', 'open');
        $job = JobPosting::create([
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'department' => $request->department,
            'location' => $request->location,
            'type' => $request->type,
            'salary_range' => $request->salary_range,
            'experience_level' => $request->experience_level,
            'description' => $request->description,
            'responsibilities' => $request->responsibilities ?? [],
            'qualifications' => $request->qualifications ?? [],
            'benefits' => $request->benefits ?? [],
            'is_active' => $status === 'open',
            'status' => $status,
            'filled_at' => $status === 'filled' ? now() : null,
            'posted_at' => $request->posted_at ?? now(),
            'application_deadline' => $request->application_deadline,
            'max_applicants' => $request->max_applicants,
        ]);

        return $this->sendResponse($job, 'Job posting created successfully.', 201);
    }

    /**
     * PUT /api/v1/admin/careers/{id}
     */
    public function update(Request $request, $id)
    {
        $job = JobPosting::where('id', $id)->orWhere('uuid', $id)->first();

        if (! $job) {
            return $this->sendError('Job posting not found.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'department' => 'sometimes|string|max:100',
            'location' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:full-time,part-time,contract,internship,remote',
            'salary_range' => 'nullable|string|max:100',
            'experience_level' => 'nullable|in:entry,mid,senior,lead,executive',
            'description' => 'sometimes|string',
            'responsibilities' => 'nullable|array',
            'qualifications' => 'nullable|array',
            'benefits' => 'nullable|array',
            'is_active' => 'boolean',
            'status' => 'nullable|in:open,filled,closed',
            'posted_at' => 'nullable|date',
            'application_deadline' => 'nullable|date',
            'max_applicants' => 'nullable|integer|min:1|max:10000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $request->except(['status']);

        if ($request->filled('title')) {
            $data['slug'] = Str::slug($request->title);
        }

        // Sync status → is_active + filled_at
        if ($request->filled('status')) {
            $newStatus = $request->status;
            $data['status'] = $newStatus;
            $data['is_active'] = $newStatus === 'open';
            $data['filled_at'] = $newStatus === 'filled' ? ($job->filled_at ?? now()) : null;
        }

        $job->update($data);

        return $this->sendResponse($job->fresh(), 'Job posting updated successfully.');
    }

    /**
     * DELETE /api/v1/admin/careers/{id}
     */
    public function destroy($id)
    {
        $job = JobPosting::where('id', $id)->orWhere('uuid', $id)->first();

        if (! $job) {
            return $this->sendError('Job posting not found.', [], 404);
        }

        $job->delete();

        return $this->sendResponse([], 'Job posting deleted successfully.');
    }

    /**
     * PATCH /api/v1/admin/careers/{id}/status
     * HR sets the job status: open | filled | closed.
     * When set to 'filled', records the timestamp so the 7-day grace period
     * begins. The listing automatically hides it after 7 days via scopeActive().
     */
    public function setStatus(Request $request, $id)
    {
        $job = JobPosting::where('id', $id)->orWhere('uuid', $id)->first();

        if (! $job) {
            return $this->sendError('Job posting not found.', [], 404);
        }

        $request->validate([
            'status' => 'required|in:open,filled,closed',
        ]);

        $newStatus = $request->status;
        $job->update([
            'status' => $newStatus,
            'is_active' => $newStatus === 'open',
            'filled_at' => $newStatus === 'filled' ? ($job->filled_at ?? now()) : null,
        ]);

        $messages = [
            'open' => 'Job posting is now open and publicly visible.',
            'filled' => 'Position marked as filled. It will remain visible for 7 days.',
            'closed' => 'Job posting closed and hidden from the public listing.',
        ];

        return $this->sendResponse($job->fresh(), $messages[$newStatus]);
    }

    /**
     * POST /api/v1/admin/careers/{id}/toggle
     * Legacy toggle — kept for backward compatibility; now syncs status too.
     */
    public function toggle($id)
    {
        $job = JobPosting::where('id', $id)->orWhere('uuid', $id)->first();

        if (! $job) {
            return $this->sendError('Job posting not found.', [], 404);
        }

        $newStatus = $job->status === 'open' ? 'closed' : 'open';
        $job->update([
            'is_active' => $newStatus === 'open',
            'status' => $newStatus,
            'filled_at' => null,
        ]);

        return $this->sendResponse($job->fresh(), $newStatus === 'open' ? 'Job posting activated.' : 'Job posting deactivated.');
    }
}
