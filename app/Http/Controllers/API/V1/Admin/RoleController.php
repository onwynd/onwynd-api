<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends BaseController
{
    public function index()
    {
        $roles = Role::all();

        return $this->sendResponse($roles, 'Roles retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:roles,name',
            'slug' => 'required|unique:roles,slug',
            'permissions' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $role = Role::create([
            'name'        => $request->name,
            'slug'        => $request->slug,
            'description' => $request->description,
            'permissions' => $request->input('permissions', []),
        ]);

        return $this->sendResponse($role, 'Role created successfully.');
    }

    public function show($id)
    {
        $role = Role::find($id);

        if (! $role) {
            return $this->sendError('Role not found.');
        }

        return $this->sendResponse($role, 'Role retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $role = Role::find($id);

        if (! $role) {
            return $this->sendError('Role not found.');
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'required|unique:roles,name,'.$id,
            'permissions' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $role->update(array_filter([
            'name'        => $request->name,
            'description' => $request->description,
            'permissions' => $request->has('permissions') ? $request->permissions : $role->permissions,
        ], fn ($v) => $v !== null));

        return $this->sendResponse($role->fresh(), 'Role updated successfully.');
    }

    public function destroy($id)
    {
        $role = Role::find($id);

        if (! $role) {
            return $this->sendError('Role not found.');
        }

        $role->delete();

        return $this->sendResponse([], 'Role deleted successfully.');
    }

    public function permissions()
    {
        // Collect all unique permissions across every role (stored as JSON arrays)
        $permissions = Role::pluck('permissions')
            ->flatten()
            ->unique()
            ->values();

        return $this->sendResponse($permissions, 'Permissions retrieved successfully.');
    }
}
