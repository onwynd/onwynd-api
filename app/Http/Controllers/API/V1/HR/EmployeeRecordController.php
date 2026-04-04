<?php

namespace App\Http\Controllers\API\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\EmployeeRecord;
use App\Models\PageViewLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeRecordController extends Controller
{
    /** GET /api/v1/hr/employee-records */
    public function index(Request $request): JsonResponse
    {
        $query = EmployeeRecord::with([
            'user:id,first_name,last_name,email,profile_photo,is_active',
            'department:id,name,code',
            'designation:id,title,level',
            'manager:id,first_name,last_name',
        ])->latest();

        if ($request->filled('department_id')) {
            $query->byDepartment($request->department_id);
        }
        if ($request->filled('employment_status')) {
            $query->where('employment_status', $request->employment_status);
        } elseif ($request->filled('status')) {
            // legacy alias
            $query->where('employment_status', $request->status);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->whereHas('user', fn ($q) =>
                $q->where('first_name', 'like', $search)
                  ->orWhere('last_name', 'like', $search)
                  ->orWhere('email', 'like', $search)
            );
        }

        // Record who viewed the sensitive employee list
        PageViewLog::record(
            userId:   $request->user()->id,
            pageKey:  'hr.employees',
            ip:       $request->ip(),
            userAgent: $request->userAgent(),
        );

        $perPage = min((int) ($request->per_page ?? 20), 200);
        return response()->json($query->paginate($perPage));
    }

    /** POST /api/v1/hr/employee-records */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Accept either an existing user_id OR new user details
            'user_id'          => 'nullable|exists:users,id|unique:employee_records,user_id',
            'first_name'       => 'required_without:user_id|string|max:100',
            'last_name'        => 'required_without:user_id|string|max:100',
            'email'            => 'required_without:user_id|email|max:255',
            'department_id'    => 'nullable|exists:departments,id',
            'designation_id'   => 'nullable|exists:designations,id',
            'manager_id'       => 'nullable|exists:users,id',
            'join_date'        => 'nullable|date',
            'probation_end_date'=> 'nullable|date',
            'contract_type'    => 'sometimes|in:full_time,part_time,contractor,intern,permanent,contract,internship,consultant',
            'employment_status'=> 'sometimes|in:active,probation,on_leave,suspended,resigned,terminated',
            'work_mode'        => 'sometimes|in:onsite,remote,hybrid',
            'office_location'  => 'nullable|string|max:200',
            'current_salary'   => 'nullable|numeric|min:0',
            'salary_currency'  => 'nullable|string|size:3',
            'notes'            => 'nullable|string|max:1000',
        ]);

        // Resolve or create the user
        if (empty($validated['user_id'])) {
            $user = \App\Models\User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'first_name' => $validated['first_name'],
                    'last_name'  => $validated['last_name'],
                    'password'   => bcrypt(\Illuminate\Support\Str::random(16)),
                ]
            );
            $validated['user_id'] = $user->id;
        }

        // Normalise contract type aliases
        $contractMap = ['full_time' => 'permanent', 'contractor' => 'contract', 'intern' => 'internship'];
        if (isset($validated['contract_type']) && isset($contractMap[$validated['contract_type']])) {
            $validated['contract_type'] = $contractMap[$validated['contract_type']];
        }

        // Remove non-model fields
        unset($validated['first_name'], $validated['last_name'], $validated['email']);

        $record = EmployeeRecord::create([
            ...$validated,
            'employee_number' => EmployeeRecord::nextEmployeeNumber(),
            'created_by'      => $request->user()->id,
        ]);

        // Sync department headcount
        if ($record->department_id) {
            $record->department->syncHeadcount();
        }

        return response()->json($record->load([
            'user:id,first_name,last_name,email',
            'department:id,name',
            'designation:id,title',
        ]), 201);
    }

    /** GET /api/v1/hr/employee-records/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $record = EmployeeRecord::with([
            'user:id,first_name,last_name,email,profile_photo,gender,date_of_birth',
            'department:id,name,code',
            'designation:id,title,level,salary_band_min,salary_band_max',
            'manager:id,first_name,last_name,email',
            'directReports.user:id,first_name,last_name',
            'directReports.designation:id,title',
        ])->findOrFail($id);

        // Track who viewed this sensitive record
        PageViewLog::record(
            userId:     $request->user()->id,
            pageKey:    'hr.employee_record',
            recordType: EmployeeRecord::class,
            recordId:   $id,
            ip:         $request->ip(),
            userAgent:  $request->userAgent(),
        );

        return response()->json($record);
    }

    /** PUT /api/v1/hr/employee-records/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $record = EmployeeRecord::findOrFail($id);
        $oldDeptId = $record->department_id;

        $validated = $request->validate([
            'department_id'    => 'nullable|exists:departments,id',
            'designation_id'   => 'nullable|exists:designations,id',
            'manager_id'       => 'nullable|exists:users,id',
            'employment_status'=> 'sometimes|in:active,probation,on_leave,suspended,resigned,terminated',
            'contract_type'    => 'sometimes|in:full_time,part_time,contractor,intern,permanent,contract,internship,consultant',
            'work_mode'        => 'sometimes|in:onsite,remote,hybrid',
            'office_location'  => 'nullable|string|max:200',
            'current_salary'   => 'nullable|numeric|min:0',
            'join_date'        => 'nullable|date',
            'confirmation_date'=> 'nullable|date',
            'exit_date'        => 'nullable|date',
            'notes'            => 'nullable|string|max:1000',
        ]);

        // Normalise contract type aliases
        $contractMap = ['full_time' => 'permanent', 'contractor' => 'contract', 'intern' => 'internship'];
        if (isset($validated['contract_type']) && isset($contractMap[$validated['contract_type']])) {
            $validated['contract_type'] = $contractMap[$validated['contract_type']];
        }

        $record->update([...$validated, 'updated_by' => $request->user()->id]);

        // Sync headcount for both old and new departments on transfer
        if (isset($validated['department_id']) && $validated['department_id'] !== $oldDeptId) {
            $record->department?->syncHeadcount();
            if ($oldDeptId) {
                \App\Models\Department::find($oldDeptId)?->syncHeadcount();
            }
        }

        return response()->json($record->fresh([
            'user:id,first_name,last_name,email',
            'department:id,name',
            'designation:id,title',
            'manager:id,first_name,last_name',
        ]));
    }

    /**
     * GET /api/v1/hr/org-chart
     * Returns the org chart tree starting from top-level managers.
     */
    public function orgChart(Request $request): JsonResponse
    {
        // Top-level: employees with no manager (or whose manager has no employee record)
        $topLevel = EmployeeRecord::with([
            'user:id,first_name,last_name,profile_photo',
            'designation:id,title,level',
            'department:id,name',
        ])
        ->active()
        ->whereNull('manager_id')
        ->orWhereDoesntHave('manager', fn ($q) =>
            $q->whereHas('employeeRecord')
        )
        ->get();

        return response()->json($this->buildTree($topLevel));
    }

    private function buildTree($nodes, int $depth = 0): array
    {
        if ($depth > 6) return []; // Safety guard against deep recursion

        return $nodes->map(function (EmployeeRecord $node) use ($depth) {
            $reports = EmployeeRecord::with([
                'user:id,first_name,last_name,profile_photo',
                'designation:id,title,level',
                'department:id,name',
            ])
            ->active()
            ->where('manager_id', $node->user_id)
            ->get();

            return [
                'id'              => $node->id,
                'employee_number' => $node->employee_number,
                'user'            => $node->user,
                'designation'     => $node->designation,
                'department'      => $node->department,
                'reports'         => $this->buildTree($reports, $depth + 1),
            ];
        })->values()->toArray();
    }

    /** DELETE /api/v1/hr/employee-records/{id} — soft-deletes (archived, recoverable) */
    public function destroy(int $id): JsonResponse
    {
        $record = EmployeeRecord::findOrFail($id);
        $deptId = $record->department_id;
        $record->delete(); // soft delete — sets deleted_at, preserves row

        if ($deptId) {
            \App\Models\Department::find($deptId)?->syncHeadcount();
        }

        return response()->json(['message' => 'Employee record archived. It can be restored by an admin.']);
    }

    /** GET /api/v1/hr/employee-records/archived — admin/ceo only */
    public function archived(Request $request): JsonResponse
    {
        $query = EmployeeRecord::onlyTrashed()->with([
            'user:id,first_name,last_name,email,is_active',
            'department:id,name,code',
            'designation:id,title,level',
        ])->latest('deleted_at');

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->whereHas('user', fn ($q) =>
                $q->where('first_name', 'like', $search)
                  ->orWhere('last_name', 'like', $search)
                  ->orWhere('email', 'like', $search)
            );
        }

        return response()->json($query->paginate(20));
    }

    /** POST /api/v1/hr/employee-records/{id}/restore — admin/ceo only */
    public function restore(int $id): JsonResponse
    {
        $record = EmployeeRecord::onlyTrashed()->findOrFail($id);
        $record->restore();

        if ($record->department_id) {
            \App\Models\Department::find($record->department_id)?->syncHeadcount();
        }

        return response()->json($record->fresh([
            'user:id,first_name,last_name,email',
            'department:id,name',
            'designation:id,title',
        ]));
    }
}
