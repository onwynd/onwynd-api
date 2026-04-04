<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\ContactSubmission;
use Illuminate\Http\Request;

class ContactSubmissionController extends BaseController
{
    public function index(Request $request)
    {
        $query = ContactSubmission::with('assignedTo:id,first_name,last_name,email')
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('subject')) {
            $query->where('subject', $request->subject);
        }
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($b) use ($q) {
                $b->where('name', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('ticket_id', 'like', "%{$q}%");
            });
        }

        $items = $query->paginate($request->integer('per_page', 25));

        $stats = [
            'total'    => ContactSubmission::count(),
            'new'      => ContactSubmission::where('status', 'new')->count(),
            'open'     => ContactSubmission::where('status', 'open')->count(),
            'replied'  => ContactSubmission::where('status', 'replied')->count(),
            'resolved' => ContactSubmission::where('status', 'resolved')->count(),
            'spam'     => ContactSubmission::where('status', 'spam')->count(),
        ];

        return $this->sendResponse([
            'submissions' => $items->items(),
            'pagination'  => [
                'total'        => $items->total(),
                'per_page'     => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
            ],
            'stats' => $stats,
        ], 'Contact submissions retrieved.');
    }

    public function show(ContactSubmission $contact)
    {
        // Auto-transition new → open when an admin views it
        if ($contact->status === 'new') {
            $contact->update(['status' => 'open']);
        }

        $contact->load('assignedTo:id,first_name,last_name,email');

        return $this->sendResponse(['submission' => $contact], 'Submission retrieved.');
    }

    public function updateStatus(Request $request, ContactSubmission $contact)
    {
        $data = $request->validate([
            'status' => 'required|in:new,open,replied,resolved,spam',
        ]);

        if ($data['status'] === 'replied' && ! $contact->replied_at) {
            $data['replied_at'] = now();
        }

        $contact->update($data);

        return $this->sendResponse(['submission' => $contact], 'Status updated.');
    }

    public function addNote(Request $request, ContactSubmission $contact)
    {
        $data = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $existing = $contact->internal_notes ? $contact->internal_notes . "\n\n" : '';
        $stamp    = now()->format('Y-m-d H:i') . ' — ';
        $contact->update(['internal_notes' => $existing . $stamp . $data['note']]);

        return $this->sendResponse(['submission' => $contact], 'Note added.');
    }

    public function destroy(ContactSubmission $contact)
    {
        $contact->delete();

        return $this->sendResponse([], 'Submission deleted.');
    }
}
