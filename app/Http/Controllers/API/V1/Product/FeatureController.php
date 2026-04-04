<?php

namespace App\Http\Controllers\API\V1\Product;

use App\Http\Controllers\API\BaseController;
use App\Models\ProductFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeatureController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = ProductFeature::query();

        // Filtering
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority') && $request->priority) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        // Sorting
        if ($request->has('sort_by') && $request->sort_by) {
            $sortDirection = $request->input('sort_direction', 'asc');
            $query->orderBy($request->sort_by, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $features = $query->get();

        return $this->sendResponse($features, 'Features retrieved successfully.');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:backlog,planned,in_progress,in_qa,completed,released',
            'priority' => 'required|in:low,medium,high,critical',
            'quarter' => 'nullable|string',
            'target_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $input = $request->all();
        // Assuming authenticated user is the requester
        $input['requested_by'] = auth()->id();

        $feature = ProductFeature::create($input);

        return $this->sendResponse($feature, 'Feature created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $feature = ProductFeature::find($id);

        if (is_null($feature)) {
            return $this->sendError('Feature not found.');
        }

        return $this->sendResponse($feature, 'Feature retrieved successfully.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $feature = ProductFeature::find($id);

        if (is_null($feature)) {
            return $this->sendError('Feature not found.');
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:backlog,planned,in_progress,in_qa,completed,released',
            'priority' => 'sometimes|required|in:low,medium,high,critical',
            'quarter' => 'nullable|string',
            'target_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $feature->update($request->all());

        return $this->sendResponse($feature, 'Feature updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $feature = ProductFeature::find($id);

        if (is_null($feature)) {
            return $this->sendError('Feature not found.');
        }

        $feature->delete();

        return $this->sendResponse([], 'Feature deleted successfully.');
    }
}
