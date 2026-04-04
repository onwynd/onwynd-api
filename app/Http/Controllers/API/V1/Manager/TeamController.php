<?php

namespace App\Http\Controllers\API\V1\Manager;

use App\Http\Controllers\API\BaseController;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class TeamController extends BaseController
{
    /**
     * Display a listing of the team members.
     */
    public function index(Request $request)
    {
        // Get employees. Filter by manager's department if applicable.
        // For now, return all users with 'employee' role.
        $query = User::whereHas('role', function ($q) {
            $q->where('slug', 'employee');
        });

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $employees = $query->paginate(10);

        return $this->sendResponse($employees, 'Team members retrieved successfully.');
    }

    /**
     * Store a newly created team member.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string',
            'department' => 'nullable|string',
            'job_title' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $input = $request->all();
        $input['password'] = Hash::make($input['password']);
        $input['is_active'] = true;

        // Assign employee role
        $role = Role::where('slug', 'employee')->first();
        if ($role) {
            $input['role_id'] = $role->id;
        }

        $user = User::create($input);

        // If we have profile/employee details table, create it here.
        // User::createProfile($user, $request->only('department', 'job_title'));

        return $this->sendResponse($user, 'Team member added successfully.');
    }

    /**
     * Display the specified team member.
     */
    public function show($id)
    {
        $user = User::find($id);

        if (is_null($user)) {
            return $this->sendError('Team member not found.');
        }

        return $this->sendResponse($user, 'Team member retrieved successfully.');
    }

    /**
     * Update the specified team member.
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (is_null($user)) {
            return $this->sendError('Team member not found.');
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,'.$id,
            'phone' => 'nullable|string',
            'department' => 'nullable|string',
            'job_title' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $user->update($request->all());

        return $this->sendResponse($user, 'Team member updated successfully.');
    }

    /**
     * Remove the specified team member.
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (is_null($user)) {
            return $this->sendError('Team member not found.');
        }

        $user->delete();

        return $this->sendResponse([], 'Team member removed successfully.');
    }
}
