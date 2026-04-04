<?php

namespace App\Http\Controllers\API\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    /** GET /api/v1/hr/departments */
    public function index(Request $request): JsonResponse
    {
        $query = Department::with(['head:id,first_name,last_name', 'parent:id,name,code'])
            ->withCount('employees');

        if ($request->boolean('tree')) {
            // Return full nested tree (root departments with recursive children)
            $depts = Department::with(['allChildren.head:id,first_name,last_name'])
                ->topLevel()
                ->active()
                ->get();
            return response()->json($depts);
        }

        if ($request->filled('parent_id')) {
            $query->where('parent_department_id', $request->parent_id);
        }
        if ($request->boolean('active_only')) {
            $query->active();
        }

        return response()->json($query->orderBy('name')->get());
    }

    /** POST /api/v1/hr/departments */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:100',
            'code'                 => 'required|string|max:20|unique:departments,code',
            'description'          => 'nullable|string|max:500',
            'head_user_id'         => 'nullable|exists:users,id',
            'parent_department_id' => 'nullable|exists:departments,id',
        ]);

        $dept = Department::create($validated);

        return response()->json($dept->load(['head:id,first_name,last_name', 'parent:id,name']), 201);
    }

    /** GET /api/v1/hr/departments/{id} */
    public function show(int $id): JsonResponse
    {
        $dept = Department::with([
            'head:id,first_name,last_name,email',
            'parent:id,name,code',
            'children:id,name,code,headcount',
            'designations:id,title,level',
            'employees.user:id,first_name,last_name,email',
            'employees.designation:id,title',
        ])->withCount('employees')->findOrFail($id);

        return response()->json($dept);
    }

    /** PUT /api/v1/hr/departments/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $dept = Department::findOrFail($id);

        $validated = $request->validate([
            'name'                 => 'sometimes|string|max:100',
            'code'                 => 'sometimes|string|max:20|unique:departments,code,' . $id,
            'description'          => 'nullable|string|max:500',
            'head_user_id'         => 'nullable|exists:users,id',
            'parent_department_id' => 'nullable|exists:departments,id',
            'is_active'            => 'sometimes|boolean',
        ]);

        $dept->update($validated);

        return response()->json($dept->fresh(['head:id,first_name,last_name', 'parent:id,name']));
    }

    /** DELETE /api/v1/hr/departments/{id} */
    public function destroy(int $id): JsonResponse
    {
        $dept = Department::withCount('employees')->findOrFail($id);
        abort_if($dept->employees_count > 0, 422, 'Cannot delete a department that has employees. Reassign them first.');

        $dept->delete();
        return response()->json(['message' => 'Department deleted.']);
    }
}
