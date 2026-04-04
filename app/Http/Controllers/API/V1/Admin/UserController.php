<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends BaseController
{
    public function index(Request $request)
    {
        $query = User::with('role');

        if ($request->has('role')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('slug', $request->role);
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->sendResponse($users, 'Users retrieved successfully.');
    }

    public function show($id)
    {
        $user = User::with(['role', 'profile', 'therapistProfile', 'patient'])->find($id);

        if (! $user) {
            return $this->sendError('User not found.');
        }

        return $this->sendResponse($user, 'User details retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
            'phone' => $request->phone,
            'is_active' => true,
        ]);

        return $this->sendResponse($user, 'User created successfully.');
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (! $user) {
            return $this->sendError('User not found.');
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'role_id' => 'sometimes|exists:roles,id',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $user->update($request->all());

        return $this->sendResponse($user, 'User updated successfully.');
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (! $user) {
            return $this->sendError('User not found.');
        }

        $user->delete();

        return $this->sendResponse([], 'User deleted successfully.');
    }
}
