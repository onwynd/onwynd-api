<?php

namespace App\Http\Controllers\API\V1\Sales;

use App\Http\Controllers\API\BaseController;
use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PipelineController extends BaseController
{
    public function index(Request $request)
    {
        // Pipeline view often groups by stage
        $stages = ['prospecting', 'negotiation', 'proposal', 'closed_won', 'closed_lost'];

        $deals = Deal::with(['lead:id,first_name,last_name,company', 'assignedUser:id,first_name,last_name'])
            ->get();

        // Calculate stats
        $totalValue = $deals->sum('value');
        $totalDeals = $deals->count();
        $wonValue = $deals->where('stage', 'closed_won')->sum('value');
        $winRate = $totalDeals > 0 ? round(($deals->where('stage', 'closed_won')->count() / $totalDeals) * 100, 2) : 0;

        $groupedDeals = $deals->groupBy('stage');

        // Ensure all stages are present even if empty
        $pipeline = [];
        $stageStats = [];

        foreach ($stages as $stage) {
            $stageDeals = $groupedDeals->get($stage, []);
            $pipeline[$stage] = $stageDeals;
            $stageStats[$stage] = [
                'count' => count($stageDeals),
                'value' => collect($stageDeals)->sum('value'),
            ];
        }

        return $this->sendResponse([
            'pipeline' => $pipeline,
            'stats' => [
                'total_value' => $totalValue,
                'total_deals' => $totalDeals,
                'won_value' => $wonValue,
                'win_rate' => $winRate.'%',
                'stage_breakdown' => $stageStats,
            ],
        ], 'Pipeline data retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|exists:leads,id',
            'title' => 'required|string|max:255',
            'value' => 'required|numeric|min:0',
            'stage' => 'required|in:prospecting,negotiation,proposal,closed_won,closed_lost',
            'probability' => 'required|integer|between:0,100',
            'expected_close_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $deal = Deal::create($request->all());

        return $this->sendResponse($deal, 'Deal created successfully.');
    }

    public function update(Request $request, $id)
    {
        $deal = Deal::find($id);

        if (! $deal) {
            return $this->sendError('Deal not found.');
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'value' => 'sometimes|numeric|min:0',
            'stage' => 'sometimes|in:prospecting,negotiation,proposal,closed_won,closed_lost',
            'probability' => 'sometimes|integer|between:0,100',
            'expected_close_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $request->all();

        if (isset($data['stage'])) {
            if ($data['stage'] === 'closed_won' || $data['stage'] === 'closed_lost') {
                $data['closed_at'] = now();
            }
        }

        $deal->update($data);

        return $this->sendResponse($deal, 'Deal updated successfully.');
    }
}
