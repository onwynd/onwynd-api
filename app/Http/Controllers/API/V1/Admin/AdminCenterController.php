<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Admin\AdminLog;
use App\Models\PhysicalCenter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCenterController extends BaseController
{
    /**
     * Display a listing of centers for admin management.
     */
    public function index(Request $request)
    {
        $centers = PhysicalCenter::query()
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('state', 'like', "%{$search}%")
                        ->orWhere('address_line1', 'like', "%{$search}%");
                });
            })
            ->when($request->status, function ($query, $status) {
                $query->where('is_active', $status === 'active');
            })
            ->when($request->city, function ($query, $city) {
                $query->where('city', $city);
            })
            ->with(['manager:id,first_name,last_name,email', 'equipment', 'services'])
            ->withCount(['checkIns as total_checkins', 'bookings as total_bookings'])
            ->paginate(20);

        return $this->sendResponse($centers, 'Centers retrieved successfully.');
    }

    /**
     * Store a newly created center.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255|unique:physical_centers,email',
            'manager_id' => 'nullable|exists:users,id',
            'capacity' => 'required|integer|min:1',
            'operating_hours' => 'required|array',
            'operating_hours.*.day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'operating_hours.*.open' => 'required|date_format:H:i',
            'operating_hours.*.close' => 'required|date_format:H:i|after:operating_hours.*.open',
            'services_offered' => 'nullable|array',
            'services_offered.*' => 'string|max:100',
            'is_active' => 'boolean',
        ]);

        $center = DB::transaction(function () use ($validated, $request) {
            $center = PhysicalCenter::create($validated);

            // Log the creation
            AdminLog::create([
                'user_id' => $request->user()->id,
                'action' => 'create_center',
                'target_type' => PhysicalCenter::class,
                'target_id' => $center->id,
                'details' => ['name' => $center->name],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $center;
        });

        return $this->sendResponse($center->load(['manager']), 'Center created successfully.');
    }

    /**
     * Display the specified center.
     */
    public function show(PhysicalCenter $center)
    {
        $center->load([
            'manager:id,name,email,phone',
            'equipment:id,center_id,equipment_type,quantity,status,last_maintenance_date',
            'services:id,center_id,service_type,description,duration_minutes,price',
            'checkIns' => function ($query) {
                $query->select('id', 'center_id', 'user_id', 'check_in_time', 'check_out_time')
                    ->with(['user:id,name,email'])
                    ->latest()
                    ->limit(10);
            },
        ])->loadCount(['checkIns', 'bookings', 'equipment']);

        return $this->sendResponse($center, 'Center details retrieved successfully.');
    }

    /**
     * Update the specified center.
     */
    public function update(Request $request, PhysicalCenter $center)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'address_line1' => 'sometimes|required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'sometimes|required|string|max:100',
            'state' => 'sometimes|required|string|max:100',
            'country' => 'sometimes|required|string|max:100',
            'postal_code' => 'sometimes|required|string|max:20',
            'phone' => 'sometimes|required|string|max:20',
            'email' => 'sometimes|required|email|max:255|unique:physical_centers,email,'.$center->id,
            'manager_id' => 'nullable|exists:users,id',
            'capacity' => 'sometimes|required|integer|min:1',
            'operating_hours' => 'sometimes|required|array',
            'operating_hours.*.day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'operating_hours.*.open' => 'required|date_format:H:i',
            'operating_hours.*.close' => 'required|date_format:H:i|after:operating_hours.*.open',
            'services_offered' => 'nullable|array',
            'services_offered.*' => 'string|max:100',
            'is_active' => 'boolean',
        ]);

        $center->update($validated);

        // Log the update
        AdminLog::create([
            'user_id' => $request->user()->id,
            'action' => 'update_center',
            'target_type' => PhysicalCenter::class,
            'target_id' => $center->id,
            'details' => ['name' => $center->name, 'changes' => array_keys($validated)],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse($center->load(['manager']), 'Center updated successfully.');
    }

    /**
     * Remove the specified center.
     */
    public function destroy(PhysicalCenter $center, Request $request)
    {
        // Check if center has any active bookings or check-ins
        if ($center->bookings()->exists() || $center->checkIns()->exists()) {
            return $this->sendError('Cannot delete center with active bookings or check-ins.', 422);
        }

        $centerName = $center->name;
        $center->delete();

        // Log the deletion
        AdminLog::create([
            'user_id' => $request->user()->id,
            'action' => 'delete_center',
            'target_type' => PhysicalCenter::class,
            'target_id' => $center->id,
            'details' => ['name' => $centerName],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse([], 'Center deleted successfully.');
    }

    /**
     * Get centers statistics for admin dashboard.
     */
    public function stats(Request $request)
    {
        $stats = [
            'total_centers' => PhysicalCenter::count(),
            'active_centers' => PhysicalCenter::where('is_active', true)->count(),
            'inactive_centers' => PhysicalCenter::where('is_active', false)->count(),
            'total_capacity' => PhysicalCenter::sum('capacity'),
            'total_checkins' => DB::table('center_check_ins')->count(),
            'total_bookings' => DB::table('center_service_bookings')->count(),
            'recently_added' => PhysicalCenter::where('created_at', '>=', now()->subDays(30))->count(),
        ];

        return $this->sendResponse($stats, 'Center statistics retrieved successfully.');
    }

    /**
     * Get available managers for assignment.
     */
    public function availableManagers(Request $request)
    {
        $managers = User::query()
            ->where(function ($query) {
                $query->whereHas('roles', function ($q) {
                    $q->where('slug', 'center_manager');
                })->orWhereHas('role', function ($q) {
                    $q->where('slug', 'center_manager');
                });
            })
            ->whereDoesntHave('managedCenters')
            ->orWhere('id', $request->current_manager_id)
            ->select('id', 'first_name', 'last_name', 'email', 'phone')
            ->get();

        // Transform managers to include full name
        $transformedManagers = $managers->map(function ($manager) {
            return [
                'id' => $manager->id,
                'name' => trim($manager->first_name.' '.$manager->last_name),
                'email' => $manager->email,
                'phone' => $manager->phone,
            ];
        });

        return $this->sendResponse($transformedManagers, 'Available managers retrieved successfully.');
    }
}
