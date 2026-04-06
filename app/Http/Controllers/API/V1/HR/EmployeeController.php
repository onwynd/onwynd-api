<?php

namespace App\Http\Controllers\API\V1\HR;

use App\Http\Controllers\API\BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class EmployeeController extends BaseController
{
    public function index(Request $request)
    {
        $employees = User::whereHas('roles', function ($q) {
            $q->where('role', 'employee');
        })
            ->with('profile')
            ->paginate($request->get('per_page', 20));

        return $this->sendResponse($employees, 'Employees retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'department' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole('employee');

        // Create profile if needed
        // $user->profile()->create([...]);

        return $this->sendResponse($user, 'Employee created successfully.');
    }

    public function show($id)
    {
        $employee = User::with('profile', 'roles')->find($id);

        if (! $employee || ! $employee->hasRole('employee')) {
            return $this->sendError('Employee not found.');
        }

        return $this->sendResponse($employee, 'Employee details retrieved.');
    }

    public function update(Request $request, $id)
    {
        $employee = User::find($id);

        if (! $employee || ! $employee->hasRole('employee')) {
            return $this->sendError('Employee not found.');
        }

        $employee->update($request->only(['first_name', 'last_name', 'email']));

        return $this->sendResponse($employee, 'Employee updated successfully.');
    }

    public function destroy($id)
    {
        $employee = User::find($id);

        if (! $employee || ! $employee->hasRole('employee')) {
            return $this->sendError('Employee not found.');
        }

        $employee->delete();

        return $this->sendResponse([], 'Employee deleted successfully.');
    }
}
