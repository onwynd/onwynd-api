<?php

namespace App\Http\Controllers\API\V1\Product;

use App\Http\Controllers\API\BaseController;
use App\Models\ProductFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoadmapController extends BaseController
{
    public function index()
    {
        $features = ProductFeature::orderBy('quarter')->orderBy('target_date')->get();

        // Group by quarter
        $grouped = $features->groupBy('quarter')->map(function ($items, $quarter) {
            return [
                'quarter' => $quarter ?: 'Unscheduled',
                'features' => $items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->title, // Frontend expects 'name'
                        'title' => $item->title,
                        'description' => $item->description,
                        'status' => $item->status,
                        'priority' => $item->priority,
                        'target_date' => $item->target_date ? $item->target_date->format('Y-m-d') : null,
                        'quarter' => $item->quarter,
                    ];
                }),
            ];
        })->values();

        return $this->sendResponse($grouped, 'Roadmap retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required',
            'priority' => 'required',
            'quarter' => 'required|string',
            'target_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $input = $request->all();
        $input['requested_by'] = auth()->id();

        $feature = ProductFeature::create($input);

        return $this->sendResponse($feature, 'Roadmap item created successfully.');
    }

    public function update(Request $request, $id)
    {
        $feature = ProductFeature::find($id);

        if (is_null($feature)) {
            return $this->sendError('Roadmap item not found.');
        }

        $feature->update($request->all());

        return $this->sendResponse($feature, 'Roadmap item updated successfully.');
    }

    public function destroy($id)
    {
        $feature = ProductFeature::find($id);

        if (is_null($feature)) {
            return $this->sendError('Roadmap item not found.');
        }

        $feature->delete();

        return $this->sendResponse([], 'Roadmap item deleted successfully.');
    }
}
