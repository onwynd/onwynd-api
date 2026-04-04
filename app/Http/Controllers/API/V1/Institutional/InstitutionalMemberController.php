<?php

namespace App\Http\Controllers\API\V1\Institutional;

use App\Http\Controllers\API\BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InstitutionalMemberController extends BaseController
{
    public function index(Request $request)
    {
        // For now, return all users with 'member' role or similar logic
        // In a real app, this would be filtered by the logged-in institution's ID
        $query = User::query();

        // Filter by role if needed, or by organization_id
        // $query->where('organization_id', $request->user()->organization_id);

        // Mock data for now if DB structure isn't fully set
        $members = $query->paginate(15);

        return $this->sendResponse($members, 'Members retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'department' => 'nullable|string',
            'status' => 'in:active,inactive,pending',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $input = $request->all();
        $input['password'] = Hash::make(Str::random(10)); // Temporary password

        // Get institutional role
        $institutionalRole = \App\Models\Role::where('slug', 'institutional')->first();
        if (! $institutionalRole) {
            return $this->sendError('Institutional role not found. Please ensure roles are seeded.');
        }
        $input['role_id'] = $institutionalRole->id;

        // $input['organization_id'] = $request->user()->organization_id;

        $user = User::create($input);

        return $this->sendResponse($user, 'Member added successfully.');
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (is_null($user)) {
            return $this->sendError('Member not found.');
        }

        $input = $request->all();

        $validator = Validator::make($input, [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,'.$id,
            'department' => 'nullable|string',
            'status' => 'in:active,inactive,pending',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        if (isset($input['first_name']) || isset($input['last_name'])) {
            $first = $input['first_name'] ?? $user->first_name;
            $last = $input['last_name'] ?? $user->last_name;
            $input['name'] = $first.' '.$last;
        }

        $user->update($input);

        return $this->sendResponse($user, 'Member updated successfully.');
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (is_null($user)) {
            return $this->sendError('Member not found.');
        }

        $user->delete();

        return $this->sendResponse([], 'Member deleted successfully.');
    }

    public function bulkImport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $file = $request->file('file');
        $path = $file->getRealPath();
        $data = array_map('str_getcsv', file($path));

        // Remove header row
        $header = array_shift($data);

        $importedCount = 0;
        $errors = [];

        foreach ($data as $index => $row) {
            // Basic validation - ensure we have enough columns
            // Assuming CSV format: first_name, last_name, email, department
            if (count($row) < 3) {
                $errors[] = 'Row '.($index + 2).': Insufficient data';

                continue;
            }

            try {
                $userData = [
                    'first_name' => $row[0],
                    'last_name' => $row[1],
                    'email' => $row[2],
                    'department' => $row[3] ?? null,
                    'password' => Hash::make(Str::random(10)),
                    // 'organization_id' => $request->user()->organization_id
                ];

                // Get institutional role for bulk import
                $institutionalRole = \App\Models\Role::where('slug', 'institutional')->first();
                if (! $institutionalRole) {
                    $errors[] = 'Row '.($index + 2).': Institutional role not found. Please ensure roles are seeded.';

                    continue;
                }
                $userData['role_id'] = $institutionalRole->id;

                // Check for duplicate email
                if (User::where('email', $userData['email'])->exists()) {
                    $errors[] = 'Row '.($index + 2).': Email '.$userData['email'].' already exists';

                    continue;
                }

                User::create($userData);
                $importedCount++;
            } catch (\Exception $e) {
                $errors[] = 'Row '.($index + 2).': '.$e->getMessage();
            }
        }

        return $this->sendResponse([
            'imported_count' => $importedCount,
            'errors' => $errors,
        ], 'Import process completed.');
    }
}
