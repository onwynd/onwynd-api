<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Community;
use App\Services\Community\CommunityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommunityController extends BaseController
{
    protected CommunityService $service;

    public function __construct(CommunityService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $communities = $this->service->list([
            'search' => $request->get('search'),
            'category' => $request->get('category'),
            'is_private' => $request->get('is_private'),
            'per_page' => $request->get('per_page', 15),
        ]);

        return $this->sendResponse($communities, 'Communities retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon_url' => 'nullable|url',
            'category' => 'nullable|string|max:100',
            'is_private' => 'boolean',
            'rules' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        $community = $this->service->create($validator->validated());

        return $this->sendResponse($community, 'Community created successfully.', 201);
    }

    public function show($id)
    {
        $community = Community::find($id);
        if (! $community) {
            return $this->sendError('Community not found.', [], 404);
        }

        return $this->sendResponse($community, 'Community retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $community = Community::find($id);
        if (! $community) {
            return $this->sendError('Community not found.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'icon_url' => 'nullable|url',
            'category' => 'nullable|string|max:100',
            'is_private' => 'boolean',
            'rules' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        $updated = $this->service->update($community, $validator->validated());

        return $this->sendResponse($updated, 'Community updated successfully.');
    }

    public function destroy($id)
    {
        $community = Community::find($id);
        if (! $community) {
            return $this->sendError('Community not found.', [], 404);
        }
        $this->service->delete($community);

        return $this->sendResponse([], 'Community deleted successfully.');
    }
}
