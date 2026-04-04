<?php

namespace App\Http\Controllers\API\V1\Sales;

use App\Http\Controllers\API\BaseController;
use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DealController extends BaseController
{
    public function index(Request $request)
    {
        $query = Deal::with(['lead:id,first_name,last_name,company', 'assignedUser:id,first_name,last_name']);
        $user = $request->user();

        // Role-based scoping
        if ($user->hasRole(['sales', 'finder'])) {
            $query->where('owner_id', $user->id);
        }

        // Closer, Relationship Manager, Admin, CEO, COO see all records by default
        if ($user->hasRole(['closer', 'relationship_manager', 'admin', 'ceo', 'coo'])) {
            // No restriction, can see everything
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('title', 'like', "%{$search}%")
                ->orWhereHas('lead', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%");
                });
        }

        if ($request->has('stage')) {
            $query->where('stage', $request->stage);
        }

        if ($request->has('owner_id')) {
            $query->where('owner_id', $request->owner_id);
        }

        if ($request->has('owner')) {
            $query->where('assigned_to', $request->owner);
        }

        if ($request->has('value_min')) {
            $query->where('value', '>=', $request->value_min);
        }

        if ($request->has('value_max')) {
            $query->where('value', '<=', $request->value_max);
        }

        $deals = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->sendResponse($deals, 'Deals retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|exists:leads,id',
            'title' => 'required|string|max:255',
            'value' => 'required|numeric|min:0',
            'stage' => 'required|string',
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

    public function show($id)
    {
        $deal = Deal::with(['lead', 'assignedUser'])->find($id);

        if (! $deal) {
            return $this->sendError('Deal not found.');
        }

        return $this->sendResponse($deal, 'Deal retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $deal = Deal::find($id);

        if (! $deal) {
            return $this->sendError('Deal not found.');
        }

        $deal->update($request->all());

        return $this->sendResponse($deal, 'Deal updated successfully.');
    }

    public function destroy($id)
    {
        $deal = Deal::find($id);

        if (! $deal) {
            return $this->sendError('Deal not found.');
        }

        $deal->delete();

        return $this->sendResponse([], 'Deal deleted successfully.');
    }
}
