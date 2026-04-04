<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\EmployeeSalary;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Employee Salary Management
 * Admin / CEO / COO can view and manage internal staff salaries.
 * These figures flow directly into Financial Statements.
 *
 * Routes:
 *   GET    /api/v1/admin/employee-salaries
 *   POST   /api/v1/admin/employee-salaries
 *   PUT    /api/v1/admin/employee-salaries/{id}
 *   DELETE /api/v1/admin/employee-salaries/{id}
 *   GET    /api/v1/admin/employee-salaries/summary
 */
class EmployeeSalaryController extends BaseController
{
    /** List all salary records with employee details. */
    public function index(Request $request): JsonResponse
    {
        $query = EmployeeSalary::with(['user:id,first_name,last_name,email', 'creator:id,first_name,last_name'])
            ->orderByDesc('effective_from');

        if ($request->boolean('active_only', true)) {
            $query->where(function ($q) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', now()->toDateString());
            });
        }

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        $salaries = $query->get()->map(fn($s) => $this->format($s));

        return $this->sendResponse([
            'data'           => $salaries,
            'monthly_total'  => (float) EmployeeSalary::active()->sum('base_salary'),
            'annual_total'   => (float) EmployeeSalary::active()->sum('base_salary') * 12,
            'headcount'      => EmployeeSalary::active()->distinct('user_id')->count('user_id'),
        ], 'Salaries retrieved.');
    }

    /** Monthly + department summary (for finance charts). */
    public function summary(): JsonResponse
    {
        $byDept = EmployeeSalary::active()
            ->selectRaw('department, SUM(base_salary) as total, COUNT(DISTINCT user_id) as headcount')
            ->groupBy('department')
            ->orderByDesc('total')
            ->get();

        $monthly = EmployeeSalary::monthlyTotal();

        return $this->sendResponse([
            'monthly_payroll'  => $monthly,
            'annual_payroll'   => $monthly * 12,
            'headcount'        => EmployeeSalary::active()->distinct('user_id')->count('user_id'),
            'by_department'    => $byDept,
        ], 'Salary summary retrieved.');
    }

    /** Create or update salary for an employee. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'        => 'required|exists:users,id',
            'base_salary'    => 'required|numeric|min:0',
            'currency'       => 'nullable|string|size:3',
            'role_label'     => 'nullable|string|max:120',
            'department'     => 'nullable|string|max:80',
            'effective_from' => 'required|date',
            'effective_to'   => 'nullable|date|after:effective_from',
            'notes'          => 'nullable|string|max:500',
        ]);

        // Close any currently open salary record for this user
        EmployeeSalary::where('user_id', $validated['user_id'])
            ->whereNull('effective_to')
            ->update(['effective_to' => now()->subDay()->toDateString()]);

        $salary = EmployeeSalary::create([
            ...$validated,
            'currency'   => $validated['currency'] ?? 'NGN',
            'created_by' => Auth::id(),
        ]);

        return $this->sendResponse($this->format($salary->load('user', 'creator')), 'Salary saved.', 201);
    }

    /** Update a specific salary record. */
    public function update(Request $request, int $id): JsonResponse
    {
        $salary = EmployeeSalary::findOrFail($id);

        $validated = $request->validate([
            'base_salary'    => 'sometimes|numeric|min:0',
            'currency'       => 'sometimes|string|size:3',
            'role_label'     => 'nullable|string|max:120',
            'department'     => 'nullable|string|max:80',
            'effective_from' => 'sometimes|date',
            'effective_to'   => 'nullable|date',
            'notes'          => 'nullable|string|max:500',
        ]);

        $salary->update($validated);

        return $this->sendResponse($this->format($salary->load('user', 'creator')), 'Salary updated.');
    }

    /** Remove a salary record. */
    public function destroy(int $id): JsonResponse
    {
        EmployeeSalary::findOrFail($id)->delete();
        return $this->sendResponse(null, 'Salary record deleted.');
    }

    /** Format for API response. */
    private function format(EmployeeSalary $s): array
    {
        return [
            'id'             => $s->id,
            'user_id'        => $s->user_id,
            'employee_name'  => $s->user ? trim("{$s->user->first_name} {$s->user->last_name}") : null,
            'employee_email' => $s->user?->email,
            'base_salary'    => (float) $s->base_salary,
            'currency'       => $s->currency,
            'role_label'     => $s->role_label,
            'department'     => $s->department,
            'effective_from' => $s->effective_from?->toDateString(),
            'effective_to'   => $s->effective_to?->toDateString(),
            'notes'          => $s->notes,
            'set_by'         => $s->creator ? trim("{$s->creator->first_name} {$s->creator->last_name}") : null,
            'created_at'     => $s->created_at?->toDateTimeString(),
        ];
    }
}
