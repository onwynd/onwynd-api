<?php

namespace App\Http\Controllers\API\V1\Therapist;

use App\Http\Controllers\API\BaseController;
use App\Models\ClientNote;
use App\Models\TherapySession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TherapistNoteController extends BaseController
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = ClientNote::where('therapist_id', $user->id);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        $notes = $query->orderBy('updated_at', 'desc')->get();

        return $this->sendResponse($notes, 'Notes retrieved successfully.');
    }

    /**
     * Return notes for a specific session's patient, scoped to the requesting therapist.
     * GET /therapist/sessions/{uuid}/notes
     */
    public function indexBySession(Request $request, string $uuid)
    {
        $user    = $request->user();
        $session = TherapySession::where('uuid', $uuid)
            ->where('therapist_id', $user->id)
            ->first();

        if (! $session) {
            return $this->sendError('Session not found or unauthorized.', [], 404);
        }

        $notes = ClientNote::where('therapist_id', $user->id)
            ->where(function ($q) use ($session) {
                $q->where('patient_id', $session->patient_id);
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        return $this->sendResponse($notes, 'Session notes retrieved.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'clientName' => 'required|string|max:255',
            'category' => 'required|string',
            'content' => 'required|string',
            'tags' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $note = ClientNote::create([
            'therapist_id' => $request->user()->id,
            'client_name' => $request->clientName,
            'category' => $request->category,
            'content' => $request->content,
            'tags' => $request->tags,
        ]);

        return $this->sendResponse($note, 'Note created successfully.');
    }

    public function update(Request $request, $id)
    {
        $note = ClientNote::where('therapist_id', $request->user()->id)->find($id);

        if (! $note) {
            return $this->sendError('Note not found.');
        }

        $validator = Validator::make($request->all(), [
            'clientName' => 'sometimes|string|max:255',
            'category' => 'sometimes|string',
            'content' => 'sometimes|string',
            'tags' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $request->only(['clientName', 'category', 'content', 'tags']);

        // Map frontend camelCase to snake_case if needed
        if ($request->has('clientName')) {
            $data['client_name'] = $request->clientName;
            unset($data['clientName']);
        }

        $note->update($data);

        return $this->sendResponse($note, 'Note updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $note = ClientNote::where('therapist_id', $request->user()->id)->find($id);

        if (! $note) {
            return $this->sendError('Note not found.');
        }

        $note->delete();

        return $this->sendResponse([], 'Note deleted successfully.');
    }
}
