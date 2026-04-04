<?php

namespace App\Http\Controllers\API\V1\HR;

use App\Http\Controllers\API\BaseController;
use App\Models\HrBenefit;
use Illuminate\Http\Request;

class BenefitsController extends BaseController
{
    public function index()
    {
        $benefits = HrBenefit::orderBy('id')->get();

        return $this->sendResponse($benefits, 'Benefits retrieved.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'icon'          => 'nullable|string|max:64',
            'status'        => 'nullable|in:active,inactive',
            'enrolled_count'=> 'nullable|integer|min:0',
        ]);

        $benefit = HrBenefit::create($data);

        return $this->sendResponse($benefit, 'Benefit created.', 201);
    }

    public function update(Request $request, HrBenefit $benefit)
    {
        $data = $request->validate([
            'title'         => 'sometimes|string|max:255',
            'description'   => 'nullable|string',
            'icon'          => 'nullable|string|max:64',
            'status'        => 'nullable|in:active,inactive',
            'enrolled_count'=> 'nullable|integer|min:0',
        ]);

        $benefit->update($data);

        return $this->sendResponse($benefit, 'Benefit updated.');
    }

    public function destroy(HrBenefit $benefit)
    {
        $benefit->delete();

        return $this->sendResponse([], 'Benefit deleted.');
    }
}
