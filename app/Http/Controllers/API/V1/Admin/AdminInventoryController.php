<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Admin\AdminLog;
use App\Models\CenterEquipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminInventoryController extends BaseController
{
    /**
     * Display a listing of inventory items.
     */
    public function index(Request $request)
    {
        $inventory = CenterEquipment::query()
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('equipment_name', 'like', "%{$search}%")
                        ->orWhere('equipment_type', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%");
                });
            })
            ->when($request->center_id, function ($query, $centerId) {
                $query->where('center_id', $centerId);
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->equipment_type, function ($query, $type) {
                $query->where('equipment_type', $type);
            })
            ->with(['center:id,name,city'])
            ->paginate(20);

        return $this->sendResponse($inventory, 'Inventory retrieved successfully.');
    }

    /**
     * Store a newly created inventory item.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'center_id' => 'required|exists:physical_centers,id',
            'equipment_type' => 'required|string|max:100',
            'equipment_name' => 'required|string|max:255',
            'serial_number' => 'nullable|string|max:100|unique:center_equipment,serial_number',
            'status' => 'required|in:active,maintenance,damaged,retired',
            'last_maintenance' => 'nullable|date',
            'next_maintenance' => 'nullable|date|after:last_maintenance',
            'purchase_date' => 'nullable|date',
            'warranty_expiry' => 'nullable|date|after:purchase_date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $equipment = CenterEquipment::create($validated);

        // Log the creation
        AdminLog::create([
            'user_id' => $request->user()->id,
            'action' => 'create_inventory',
            'target_type' => CenterEquipment::class,
            'target_id' => $equipment->id,
            'details' => [
                'equipment_name' => $equipment->equipment_name,
                'center' => $equipment->center->name,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse($equipment->load(['center']), 'Inventory item created successfully.');
    }

    /**
     * Display the specified inventory item.
     */
    public function show(CenterEquipment $inventory)
    {
        $inventory->load(['center:id,name,city,address_line1']);

        return $this->sendResponse($inventory, 'Inventory item retrieved successfully.');
    }

    /**
     * Update the specified inventory item.
     */
    public function update(Request $request, CenterEquipment $inventory)
    {
        $validated = $request->validate([
            'center_id' => 'sometimes|required|exists:physical_centers,id',
            'equipment_type' => 'sometimes|required|string|max:100',
            'equipment_name' => 'sometimes|required|string|max:255',
            'serial_number' => 'nullable|string|max:100|unique:center_equipment,serial_number,'.$inventory->id,
            'status' => 'sometimes|required|in:active,maintenance,damaged,retired',
            'last_maintenance' => 'nullable|date',
            'next_maintenance' => 'nullable|date|after:last_maintenance',
            'purchase_date' => 'nullable|date',
            'warranty_expiry' => 'nullable|date|after:purchase_date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $inventory->update($validated);

        // Log the update
        AdminLog::create([
            'user_id' => $request->user()->id,
            'action' => 'update_inventory',
            'target_type' => CenterEquipment::class,
            'target_id' => $inventory->id,
            'details' => [
                'equipment_name' => $inventory->equipment_name,
                'changes' => array_keys($validated),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse($inventory->load(['center']), 'Inventory item updated successfully.');
    }

    /**
     * Remove the specified inventory item.
     */
    public function destroy(CenterEquipment $inventory, Request $request)
    {
        $equipmentName = $inventory->equipment_name;
        $centerName = $inventory->center->name;

        $inventory->delete();

        // Log the deletion
        AdminLog::create([
            'user_id' => $request->user()->id,
            'action' => 'delete_inventory',
            'target_type' => CenterEquipment::class,
            'target_id' => $inventory->id,
            'details' => [
                'equipment_name' => $equipmentName,
                'center' => $centerName,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse([], 'Inventory item deleted successfully.');
    }

    /**
     * Get inventory statistics.
     */
    public function stats(Request $request)
    {
        $stats = [
            'total_items' => CenterEquipment::count(),
            'active_items' => CenterEquipment::where('status', 'active')->count(),
            'maintenance_items' => CenterEquipment::where('status', 'maintenance')->count(),
            'damaged_items' => CenterEquipment::where('status', 'damaged')->count(),
            'retired_items' => CenterEquipment::where('status', 'retired')->count(),
            'warranty_expiring_soon' => CenterEquipment::where('warranty_expiry', '<=', now()->addDays(30))
                ->where('warranty_expiry', '>=', now())
                ->count(),
            'maintenance_due' => CenterEquipment::where('next_maintenance', '<=', now())->count(),
        ];

        return $this->sendResponse($stats, 'Inventory statistics retrieved successfully.');
    }

    /**
     * Get equipment types summary.
     */
    public function equipmentTypes(Request $request)
    {
        $types = CenterEquipment::query()
            ->select('equipment_type', DB::raw('count(*) as total'))
            ->groupBy('equipment_type')
            ->get()
            ->map(function ($type) {
                return [
                    'type' => $type->equipment_type,
                    'total' => $type->total,
                    'active' => CenterEquipment::where('equipment_type', $type->equipment_type)
                        ->where('status', 'active')
                        ->count(),
                ];
            });

        return $this->sendResponse($types, 'Equipment types retrieved successfully.');
    }

    /**
     * Bulk update equipment status.
     */
    public function bulkUpdateStatus(Request $request)
    {
        $validated = $request->validate([
            'equipment_ids' => 'required|array',
            'equipment_ids.*' => 'exists:center_equipment,id',
            'status' => 'required|in:active,maintenance,damaged,retired',
        ]);

        $updatedCount = CenterEquipment::whereIn('id', $validated['equipment_ids'])
            ->update(['status' => $validated['status']]);

        // Log the bulk update
        AdminLog::create([
            'user_id' => $request->user()->id,
            'action' => 'bulk_update_inventory_status',
            'target_type' => CenterEquipment::class,
            'target_id' => null,
            'details' => [
                'updated_count' => $updatedCount,
                'new_status' => $validated['status'],
                'equipment_ids' => $validated['equipment_ids'],
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse(['updated_count' => $updatedCount], 'Equipment status updated successfully.');
    }
}
