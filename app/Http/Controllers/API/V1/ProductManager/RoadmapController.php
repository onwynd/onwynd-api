<?php

namespace App\Http\Controllers\API\V1\ProductManager;

use App\Http\Controllers\API\BaseController;
use App\Models\ProductFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RoadmapController extends BaseController
{
    public function index()
    {
        // Group features by quarter
        $features = ProductFeature::whereNotNull('quarter')
            ->orderBy('quarter')
            ->get()
            ->groupBy('quarter');

        $roadmap = [];
        foreach ($features as $quarter => $items) {
            $roadmap[] = [
                'quarter' => $quarter,
                'features' => $items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->title,
                        'status' => $item->status,
                        'priority' => $item->priority,
                        'description' => $item->description,
                        'target_date' => $item->target_date,
                    ];
                })->values(),
            ];
        }

        return $this->sendResponse($roadmap, 'Product roadmap retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'quarter' => 'required|string', // Roadmap items must have a quarter
            'status' => 'required|string',
            'priority' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $input = $request->all();
        $input['requested_by'] = Auth::id();

        $feature = ProductFeature::create($input);

        return $this->sendResponse($feature, 'Roadmap item created successfully.');
    }

    public function update(Request $request, $id)
    {
        $feature = ProductFeature::find($id);
        if (is_null($feature)) {
            return $this->sendError('Roadmap item not found.');
        }

        $input = $request->all();
        $feature->update($input);

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
