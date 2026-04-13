<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Http\DTOs\UserDTO;
use App\Models\Admin\AdminLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends BaseController
{
    public function index(Request $request)
    {
        $users = User::with(['role', 'activeSubscription.plan'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->when($request->role, function ($query, $role) {
                $query->whereHas('role', function ($q) use ($role) {
                    $q->where('slug', $role);
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $users->getCollection()->transform(function ($user) {
            return UserDTO::fromModel($user);
        });

        return $this->sendResponse($users, 'Users retrieved successfully.');
    }

    public function show(User $user)
    {
        $user->load(['roles', 'profile', 'activeSubscription.plan']);

        return $this->sendResponse(UserDTO::fromModel($user), 'User details retrieved successfully.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
            'is_active' => 'nullable|boolean',
            'student_verification_status' => 'nullable|in:pending,approved,rejected',
        ]);

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => $data['first_name'].' '.$data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'student_verification_status' => $data['student_verification_status'] ?? null,
        ]);

        if (! empty($data['role'])) {
            $role = \App\Models\Role::where('slug', $data['role'])->first();
            if ($role) {
                $user->update(['role_id' => $role->id]);
            }
        }

        AdminLog::create([
            'user_id' => $request->user()->id,
            'action' => 'create_user',
            'target_type' => User::class,
            'target_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse($user->load('roles'), 'User created successfully.', 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
            'is_active' => 'nullable|boolean',
            'student_verification_status' => 'nullable|in:pending,approved,rejected',
        ]);

        $updateData = collect($data)
            ->only(['first_name', 'last_name', 'email', 'phone', 'is_active', 'student_verification_status'])
            ->filter(fn ($v, $k) => $v !== null || $k === 'is_active')
            ->toArray();

        if (! empty($data['first_name']) || ! empty($data['last_name'])) {
            $updateData['name'] = ($data['first_name'] ?? $user->first_name).' '.($data['last_name'] ?? $user->last_name);
        }

        if (! empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        if (array_key_exists('role', $data) && ! empty($data['role'])) {
            $role = \App\Models\Role::where('slug', $data['role'])->first();
            if ($role) {
                $user->update(['role_id' => $role->id]);
            }
        }

        AdminLog::create([
            'user_id' => $request->user()->id,
            'action' => 'update_user',
            'target_type' => User::class,
            'target_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse($user->fresh()->load('roles'), 'User updated successfully.');
    }

    public function suspend(Request $request, User $user)
    {
        $user->update(['is_active' => false]);

        AdminLog::create([
            'user_id' => $request->user()->id,
            'action' => 'suspend_user',
            'target_type' => User::class,
            'target_id' => $user->id,
            'details' => ['reason' => $request->reason],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse($user, 'User suspended successfully.');
    }

    public function activate(Request $request, User $user)
    {
        $user->update(['is_active' => true]);

        AdminLog::create([
            'user_id' => $request->user()->id,
            'action' => 'activate_user',
            'target_type' => User::class,
            'target_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse($user, 'User activated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        $user->delete();

        AdminLog::create([
            'user_id' => $request->user()->id,
            'action' => 'delete_user',
            'target_type' => User::class,
            'target_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse(null, 'User deleted successfully.');
    }

    /**
     * List users with pending/approved/rejected student verifications.
     *
     * GET /api/v1/admin/student-verifications
     */
    public function getStudentVerifications(Request $request)
    {
        $query = User::query()
            ->whereNotNull('student_verification_status')
            ->select([
                'id',
                'first_name',
                'last_name',
                'email',
                'student_verification_status',
                'student_email',
                'student_id',
                'student_verified_at',
                'institution_name',
                'created_at',
            ]);

        if ($request->filled('status')) {
            $query->where('student_verification_status', $request->status);
        }

        return $this->sendResponse($query->latest()->paginate(20), 'Student verifications retrieved.');
    }

    /**
     * Grant or revoke a secondary role for a user (W.3).
     */
    public function updateRoles(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => 'required|string',
            'action' => 'required|in:grant,revoke',
        ]);

        if ($data['action'] === 'grant') {
            // Check if user already has the role
            if ($user->roles()->where('role', $data['role'])->exists()) {
                return $this->sendError('User already has this role.', [], 400);
            }

            $user->roles()->create([
                'role' => $data['role'],
                'granted_by' => $request->user()->id,
                'granted_at' => now(),
            ]);

            // Special handling for therapist role
            if ($data['role'] === 'therapist') {
                // Ensure therapist profile exists
                \App\Models\Therapist::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'status' => 'pending',
                        'is_verified' => false,
                        'is_accepting_clients' => false,
                    ]
                );

                $user->notify(new \App\Notifications\TherapistProfileCompletion($user->first_name));
            }
        } else {
            // Revoke role
            $user->roles()->where('role', $data['role'])->delete();
        }

        AdminLog::create([
            'user_id' => $request->user()->id,
            'action' => $data['action'].'_role',
            'target_type' => User::class,
            'target_id' => $user->id,
            'details' => ['role' => $data['role']],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse($user->fresh()->load('roles'), 'User roles updated successfully.');
    }
}
