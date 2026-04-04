<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\MindfulResource;
use App\Models\ResourceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ResourceController extends BaseController
{
    public function index(Request $request)
    {
        $query = MindfulResource::with(['category', 'submitter']);

        if ($request->has('category_id')) {
            $query->where('resource_category_id', $request->category_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $resources = $query->latest()->paginate(20);

        return $this->sendResponse($resources, 'Resources retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'resource_category_id' => 'required|exists:resource_categories,id',
            'title' => 'required|string|max:255',
            'type' => 'required|in:article,video,audio',
            'content' => 'nullable|string',
            'media_url' => 'nullable|url',
            'thumbnail_url' => 'nullable|url',
            'duration_seconds' => 'nullable|integer',
            'is_premium' => 'boolean',
            'status' => 'in:draft,pending',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // Determine status: Admin can publish? User says "admin would only approve".
        // Let's enforce that ALL creations go to pending (or draft), and Admins MUST approve them.
        // Or if the user IS admin, maybe they are just approving others, but if they create one themselves?
        // Let's stick to the prompt: "admin would only approve...".
        // So creation should probably be restricted to Manager/Data Entry or default to pending for everyone.
        // But if Admin creates, defaulting to pending and then self-approving is fine.
        // Actually, if "admin would only approve", maybe I should block Admin from creation?
        // "company users asides admin could be manager or data entry specialist... not just therapist to create resources... a user role from our company can handle that instead"
        // This implies Admins MIGHT NOT be the ones creating.
        // However, usually Admins have full access. I will default to 'pending' for EVERYONE to be safe and consistent with the workflow.

        $status = $request->status ?? 'pending';

        $resource = MindfulResource::create(array_merge(
            $request->all(),
            [
                'slug' => Str::slug($request->title).'-'.uniqid(),
                'status' => $status,
                'submitted_by' => $request->user()->id,
            ]
        ));

        return $this->sendResponse($resource, 'Resource submitted successfully.');
    }

    public function show($id)
    {
        $resource = MindfulResource::with('category')->find($id);

        if (! $resource) {
            return $this->sendError('Resource not found.');
        }

        return $this->sendResponse($resource, 'Resource retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $resource = MindfulResource::find($id);

        if (! $resource) {
            return $this->sendError('Resource not found.');
        }

        $validator = Validator::make($request->all(), [
            'resource_category_id' => 'exists:resource_categories,id',
            'title' => 'string|max:255',
            'type' => 'in:article,video,audio',
            'content' => 'nullable|string',
            'media_url' => 'nullable|url',
            'thumbnail_url' => 'nullable|url',
            'duration_seconds' => 'nullable|integer',
            'is_premium' => 'boolean',
            'status' => 'in:draft,pending,published,rejected',
            'admin_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        if ($request->has('title')) {
            $request->merge(['slug' => Str::slug($request->title).'-'.uniqid()]);
        }

        $resource->update($request->all());

        return $this->sendResponse($resource, 'Resource updated successfully.');
    }

    public function approve(Request $request, $id)
    {
        // Ensure only admin can approve
        if (! $request->user()->hasRole('admin')) {
            return $this->sendError('Unauthorized. Only Admins can approve resources.', [], 403);
        }

        $resource = MindfulResource::find($id);

        if (! $resource) {
            return $this->sendError('Resource not found.');
        }

        $resource->update([
            'status' => 'published',
            'admin_note' => null,
        ]);

        return $this->sendResponse($resource, 'Resource approved and published.');
    }

    public function reject(Request $request, $id)
    {
        // Ensure only admin can reject
        if (! $request->user()->hasRole('admin')) {
            return $this->sendError('Unauthorized. Only Admins can reject resources.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'admin_note' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $resource = MindfulResource::find($id);

        if (! $resource) {
            return $this->sendError('Resource not found.');
        }

        $resource->update([
            'status' => 'rejected',
            'admin_note' => $request->admin_note,
        ]);

        return $this->sendResponse($resource, 'Resource rejected.');
    }

    public function destroy($id)
    {
        $resource = MindfulResource::find($id);

        if (! $resource) {
            return $this->sendError('Resource not found.');
        }

        $resource->delete();

        return $this->sendResponse([], 'Resource deleted successfully.');
    }

    // Category Management
    public function storeCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $category = ResourceCategory::create(array_merge(
            $request->all(),
            ['slug' => Str::slug($request->name)]
        ));

        return $this->sendResponse($category, 'Category created successfully.');
    }
}
