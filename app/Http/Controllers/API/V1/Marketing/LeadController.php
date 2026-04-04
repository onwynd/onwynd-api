<?php

namespace App\Http\Controllers\API\V1\Marketing;

use App\Http\Controllers\API\BaseController;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeadController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Lead::with('assignedUser:id,first_name,last_name');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        if ($request->has('source')) {
            $query->where('source', $request->source);
        }

        $leads = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->sendResponse($leads, 'Leads retrieved successfully.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:leads,email',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'status' => 'required|in:new,contacted,qualified,lost,won',
            'source' => 'nullable|string|max:255',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $lead = Lead::create($request->all());

        return $this->sendResponse($lead, 'Lead created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $lead = Lead::with(['assignedUser', 'deals'])->find($id);

        if (! $lead) {
            return $this->sendError('Lead not found.');
        }

        return $this->sendResponse($lead, 'Lead details retrieved successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $lead = Lead::find($id);

        if (! $lead) {
            return $this->sendError('Lead not found.');
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:leads,email,'.$id,
            'status' => 'sometimes|in:new,contacted,qualified,lost,won',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $lead->update($request->all());

        return $this->sendResponse($lead, 'Lead updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $lead = Lead::find($id);

        if (! $lead) {
            return $this->sendError('Lead not found.');
        }

        $lead->delete();

        return $this->sendResponse([], 'Lead deleted successfully.');
    }
}
