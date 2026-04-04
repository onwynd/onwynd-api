<?php

namespace App\Http\Controllers\API\V1\Sales;

use App\Http\Controllers\API\BaseController;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeadController extends BaseController
{
    public function index(Request $request)
    {
        $query = Lead::with('assignedUser:id,first_name,last_name');
        $user = $request->user();

        // Role-based scoping
        if ($user->hasRole(['sales', 'finder'])) {
            if ($request->boolean('unassigned')) {
                $query->whereNull('owner_id');
            } else {
                $query->where('owner_id', $user->id);
            }
        }

        // Closer, Relationship Manager, Admin, CEO, COO see all records by default
        if ($user->hasRole(['closer', 'relationship_manager', 'admin', 'ceo', 'coo'])) {
            // No restriction, can see everything
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

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('owner_id')) {
            $query->where('owner_id', $request->owner_id);
        }

        if ($request->has('unassigned') && $request->unassigned) {
            $query->whereNull('owner_id');
        }

        // Keep legacy filter support
        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $leads = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->sendResponse($leads, 'Leads retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:leads,email',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'status' => 'required|in:new,contacted,qualified,lost',
            'source' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $lead = Lead::create($request->all());

        return $this->sendResponse($lead, 'Lead created successfully.');
    }

    public function show($id)
    {
        $lead = Lead::with(['assignedUser', 'deals'])->find($id);

        if (! $lead) {
            return $this->sendError('Lead not found.');
        }

        return $this->sendResponse($lead, 'Lead details retrieved successfully.');
    }

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
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'status' => 'sometimes|in:new,contacted,qualified,lost',
            'source' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $lead->update($request->all());

        return $this->sendResponse($lead, 'Lead updated successfully.');
    }

    public function handoff(Request $request, $id)
    {
        $lead = Lead::find($id);

        if (! $lead) {
            return $this->sendError('Lead not found.');
        }

        $validator = Validator::make($request->all(), [
            'assigned_to' => 'required|exists:users,id',
            'handoff_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $user = $request->user();
        $rmId = $request->assigned_to;
        $rm = \App\Models\User::find($rmId);

        $lead->update([
            'owner_id' => $rmId,
            'assigned_to' => $rmId,
            'handoff_note' => $request->handoff_note,
            'handed_off_at' => now(),
            'handed_off_by' => $user->id,
        ]);

        // Log Activity
        \App\Models\UserActivity::create([
            'user_id' => $user->id,
            'activity_type' => 'handoff',
            'description' => "Handed off lead {$lead->company} to {$rm->first_name} {$rm->last_name}",
            'metadata' => [
                'lead_id' => $lead->id,
                'from_id' => $user->id,
                'to_id' => $rmId,
                'note' => $request->handoff_note,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // In-app Notification
        \App\Models\Notification::create([
            'user_id' => $rmId,
            'type' => 'lead_handoff',
            'title' => 'New Lead Assigned',
            'message' => "{$user->first_name} {$user->last_name} handed off {$lead->company} to you",
            'action_url' => "/sales/leads/{$lead->id}",
            'data' => ['lead_id' => $lead->id],
        ]);

        // Email
        try {
            \Illuminate\Support\Facades\Mail::raw(
                "You have been assigned a new lead.\n\n".
                "Company: {$lead->company}\n".
                "Contact: {$lead->first_name} {$lead->last_name}\n".
                "Email: {$lead->email}\n".
                "Stage: {$lead->status}\n".
                "Handoff Note: {$request->handoff_note}\n\n".
                'View Lead: '.config('app.url')."/sales/leads/{$lead->id}",
                function ($message) use ($rm, $lead) {
                    $message->to($rm->email)
                        ->subject("New lead assigned — {$lead->company}");
                }
            );
        } catch (\Exception $e) {
            \Log::error('Failed to send handoff email: '.$e->getMessage());
        }

        return $this->sendResponse($lead, 'Lead handed off successfully.');
    }

    public function assignMe(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);
        $lead->update(['owner_id' => $request->user()->id]);

        return $this->sendResponse($lead, 'Lead assigned to you.');
    }

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
