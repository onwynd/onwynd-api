<?php

namespace App\Http\Controllers\API\V1\Institutional;

use App\Http\Controllers\API\BaseController;
use App\Models\Institutional\Organization;
use App\Services\Institutional\BulkImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MemberController extends BaseController
{
    protected $importService;

    public function __construct(BulkImportService $importService)
    {
        $this->importService = $importService;
    }

    public function index(Request $request, Organization $organization)
    {
        // Check access
        if (! $this->canAccess($organization)) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $members = $organization->members()->with('user')->paginate(20);

        return $this->sendResponse($members, 'Members retrieved.');
    }

    public function update(Request $request, Organization $organization, $id)
    {
        // Check access
        if (! $this->canAccess($organization)) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $member = $organization->members()->where('id', $id)->first();
        if (! $member) {
            return $this->sendError('Member not found.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'role' => 'sometimes|string',
            'status' => 'sometimes|in:active,inactive',
            'department' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $member->update($request->only(['role', 'status', 'department']));

        return $this->sendResponse($member, 'Member updated successfully.');
    }

    public function bulkImport(Request $request, Organization $organization)
    {
        // Check access
        if (! $this->canAccess($organization)) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt,xlsx',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        try {
            $result = $this->importService->importMembers($organization, $request->file('file'));

            return $this->sendResponse($result, 'Members imported successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Import failed.', ['error' => $e->getMessage()]);
        }
    }

    protected function canAccess(Organization $organization)
    {
        $user = Auth::user();
        if ($user->role === 'admin') {
            return true;
        }

        return $organization->members()
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->exists();
    }
}
