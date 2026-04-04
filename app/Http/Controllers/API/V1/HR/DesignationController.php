<?php

namespace App\Http\Controllers\API\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    /** GET /api/v1/hr/designations */
    public function index(Request $request): JsonResponse
    {
        $query = Designation::with([
            'department:id,name,code',
            'reportsTo:id,title',
        ])->withCount('employees');

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->boolean('active_only')) {
            $query->active();
        }

        return response()->json($query->orderBy('level')->orderBy('title')->get());
    }

    /** POST /api/v1/hr/designations */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'                    => 'required|string|max:100',
            'code'                     => 'required|string|max:30|unique:designations,code',
            'level'                    => 'required|integer|min:1|max:10',
            'department_id'            => 'nullable|exists:departments,id',
            'reports_to_designation_id'=> 'nullable|exists:designations,id',
            'salary_band_min'          => 'nullable|numeric|min:0',
            'salary_band_max'          => 'nullable|numeric|gte:salary_band_min',
            'currency'                 => 'nullable|string|size:3',
            'description'              => 'nullable|string|max:500',
        ]);

        $designation = Designation::create($validated);

        return response()->json($designation->load(['department:id,name', 'reportsTo:id,title']), 201);
    }

    /** GET /api/v1/hr/designations/{id} */
    public function show(int $id): JsonResponse
    {
        $designation = Designation::with([
            'department:id,name,code',
            'reportsTo:id,title,level',
            'directReports:id,title,level',
            'employees.user:id,first_name,last_name',
        ])->withCount('employees')->findOrFail($id);

        return response()->json($designation);
    }

    /** PUT /api/v1/hr/designations/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $designation = Designation::findOrFail($id);

        $validated = $request->validate([
            'title'                    => 'sometimes|string|max:100',
            'code'                     => 'sometimes|string|max:30|unique:designations,code,' . $id,
            'level'                    => 'sometimes|integer|min:1|max:10',
            'department_id'            => 'nullable|exists:departments,id',
            'reports_to_designation_id'=> 'nullable|exists:designations,id',
            'salary_band_min'          => 'nullable|numeric|min:0',
            'salary_band_max'          => 'nullable|numeric',
            'description'              => 'nullable|string|max:500',
            'is_active'                => 'sometimes|boolean',
        ]);

        $designation->update($validated);

        return response()->json($designation->fresh(['department:id,name', 'reportsTo:id,title']));
    }

    /** DELETE /api/v1/hr/designations/{id} */
    public function destroy(int $id): JsonResponse
    {
        $designation = Designation::withCount('employees')->findOrFail($id);
        abort_if($designation->employees_count > 0, 422, 'Cannot delete a designation with active employees.');
        $designation->delete();
        return response()->json(['message' => 'Designation deleted.']);
    }
}
