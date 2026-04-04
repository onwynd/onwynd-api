<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Repositories\Contracts\TherapyRepositoryInterface;
use Illuminate\Http\Request;

class SessionController extends BaseController
{
    protected $therapyRepository;

    public function __construct(TherapyRepositoryInterface $therapyRepository)
    {
        $this->therapyRepository = $therapyRepository;
    }

    public function index(Request $request)
    {
        $sessions = $this->therapyRepository->getAllSessions($request->all());

        return $this->sendResponse($sessions, 'All sessions retrieved successfully.');
    }

    public function show($id)
    {
        $session = $this->therapyRepository->find($id);

        if (! $session) {
            return $this->sendError('Session not found.');
        }

        $session->load(['patient', 'therapist', 'sessionNote']);

        return $this->sendResponse($session, 'Session details retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $session = $this->therapyRepository->find($id);

        if (! $session) {
            return $this->sendError('Session not found.');
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,confirmed,completed,cancelled,no_show',
            'scheduled_at' => 'sometimes|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $this->therapyRepository->update($id, $request->all());
        $session->refresh();

        return $this->sendResponse($session, 'Session updated successfully.');
    }

    public function destroy($id)
    {
        $session = $this->therapyRepository->find($id);

        if (! $session) {
            return $this->sendError('Session not found.');
        }

        $this->therapyRepository->delete($id);

        return $this->sendResponse([], 'Session deleted successfully.');
    }
}
