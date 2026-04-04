<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\MindfulnessActivity;
use App\Models\MindfulResource;
use App\Models\ResourceCategory;
use App\Services\OnwyndScoreService;
use Illuminate\Http\Request;

class ResourceController extends BaseController
{
    protected $scoreService;

    public function __construct(OnwyndScoreService $scoreService)
    {
        $this->scoreService = $scoreService;
    }

    public function index(Request $request)
    {
        $query = MindfulResource::with('category')->where('status', 'published');

        if ($request->has('category_id')) {
            $query->where('resource_category_id', $request->category_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('search')) {
            $query->where('title', 'like', '%'.$request->search.'%');
        }

        $resources = $query->paginate(10);

        return $this->sendResponse($resources, 'Resources retrieved successfully.');
    }

    public function categories()
    {
        $categories = ResourceCategory::where('is_active', true)->get();

        return $this->sendResponse($categories, 'Resource categories retrieved successfully.');
    }

    public function show($id)
    {
        $resource = MindfulResource::with('category')->find($id);

        if (! $resource || $resource->status !== 'published') {
            return $this->sendError('Resource not found.');
        }

        // Increment view count
        $resource->increment('views_count');

        return $this->sendResponse($resource, 'Resource retrieved successfully.');
    }

    public function complete(Request $request, $id)
    {
        $resource = MindfulResource::find($id);

        if (! $resource) {
            return $this->sendError('Resource not found.');
        }

        // Log as Mindfulness Activity to boost score
        MindfulnessActivity::create([
            'user_id' => $request->user()->id,
            'title' => 'Completed: '.$resource->title,
            'type' => 'education', // or 'resource'
            'duration_seconds' => $resource->duration_seconds ?? 300, // Default 5 mins if not set
            'completed_at' => now(),
            'notes' => 'Completed resource from library.',
        ]);

        // Update Score
        $this->scoreService->updateScore($request->user());

        return $this->sendResponse([], 'Resource marked as completed and score updated.');
    }
}
