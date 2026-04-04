<?php

namespace App\Http\Controllers\API\V1\Marketing;

use App\Http\Controllers\API\BaseController;
use App\Models\MarketingCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CampaignController extends BaseController
{
    public function index(Request $request)
    {
        $query = MarketingCampaign::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $campaigns = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->sendResponse($campaigns, 'Campaigns retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'status' => 'required|in:draft,active,paused,completed',
            'budget' => 'nullable|numeric',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $campaign = MarketingCampaign::create($request->all());

        return $this->sendResponse($campaign, 'Campaign created successfully.');
    }

    public function show($id)
    {
        $campaign = MarketingCampaign::find($id);

        if (! $campaign) {
            return $this->sendError('Campaign not found.');
        }

        return $this->sendResponse($campaign, 'Campaign details retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $campaign = MarketingCampaign::find($id);

        if (! $campaign) {
            return $this->sendError('Campaign not found.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string',
            'status' => 'sometimes|in:draft,active,paused,completed',
            'budget' => 'nullable|numeric',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $campaign->update($request->all());

        return $this->sendResponse($campaign, 'Campaign updated successfully.');
    }

    public function destroy($id)
    {
        $campaign = MarketingCampaign::find($id);

        if (! $campaign) {
            return $this->sendError('Campaign not found.');
        }

        $campaign->delete();

        return $this->sendResponse([], 'Campaign deleted successfully.');
    }
}
