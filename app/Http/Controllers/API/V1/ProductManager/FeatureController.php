<?php

namespace App\Http\Controllers\API\V1\ProductManager;

use App\Http\Controllers\API\BaseController;
use App\Models\ProductFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FeatureController extends BaseController
{
    public function index(Request $request)
    {
        $query = ProductFeature::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->has('quarter')) {
            $query->where('quarter', $request->quarter);
        }

        $features = $query->orderBy('created_at', 'desc')->paginate(10);

        return $this->sendResponse($features, 'Features list retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'status' => 'required|string',
            'priority' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $input = $request->all();
        $input['requested_by'] = Auth::id();

        $feature = ProductFeature::create($input);

        return $this->sendResponse($feature, 'Feature created successfully.');
    }

    public function show($id)
    {
        $feature = ProductFeature::find($id);
        if (is_null($feature)) {
            return $this->sendError('Feature not found.');
        }

        return $this->sendResponse($feature, 'Feature details retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $feature = ProductFeature::find($id);
        if (is_null($feature)) {
            return $this->sendError('Feature not found.');
        }

        $input = $request->all();
        $feature->update($input);

        return $this->sendResponse($feature, 'Feature updated successfully.');
    }

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
