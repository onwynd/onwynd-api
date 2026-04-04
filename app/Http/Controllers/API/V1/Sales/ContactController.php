<?php

namespace App\Http\Controllers\API\V1\Sales;

use App\Http\Controllers\API\BaseController;
use App\Models\Lead;
use Illuminate\Http\Request;

/**
 * Sales Contacts = Leads viewed as individual people contacts.
 * Re-uses the leads table but returns contact-shaped data.
 */
class ContactController extends BaseController
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Lead::query();

        // Scope to own leads for finder/sales; closer/RM/admin see all contacts
        if ($user->hasRole(['finder', 'sales'])) {
            $query->where('owner_id', $user->id);
        }

        if ($request->filled('company')) {
            $query->where('company', 'like', '%'.$request->company.'%');
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('company', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $leads = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->sendResponse($leads, 'Contacts retrieved.');
    }

    public function show($id)
    {
        $lead = Lead::find($id);
        if (! $lead) {
            return $this->sendError('Contact not found.');
        }

        return $this->sendResponse($lead, 'Contact retrieved.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:200',
            'phone' => 'nullable|string|max:30',
            'company' => 'nullable|string|max:200',
            'source' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:50',
        ]);

        $email = strtolower(trim($data['email']));
        $lead = Lead::where('email', $email)->first();
        if ($lead) {
            $lead->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? $lead->phone,
                'company' => $data['company'] ?? $lead->company,
                'source' => $data['source'] ?? $lead->source,
                'status' => $data['status'] ?? $lead->status,
            ]);
        } else {
            $lead = Lead::create([
                ...$data,
                'email' => $email,
            ]);
        }

        return $this->sendResponse($lead, 'Contact created.');
    }

    public function update(Request $request, $id)
    {
        $lead = Lead::find($id);
        if (! $lead) {
            return $this->sendError('Contact not found.');
        }
        $lead->update($request->all());

        return $this->sendResponse($lead, 'Contact updated.');
    }

    public function destroy($id)
    {
        $lead = Lead::find($id);
        if (! $lead) {
            return $this->sendError('Contact not found.');
        }
        $lead->delete();

        return $this->sendResponse([], 'Contact deleted.');
    }
}
