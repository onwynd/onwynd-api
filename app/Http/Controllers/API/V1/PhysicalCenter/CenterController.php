<?php

namespace App\Http\Controllers\API\V1\PhysicalCenter;

use App\Http\Controllers\API\BaseController;
use App\Models\CenterService;
use App\Models\PhysicalCenter;
use Illuminate\Http\Request;

class CenterController extends BaseController
{
    /**
     * Display a listing of active centers with optional filters.
     */
    public function index(Request $request)
    {
        $query = PhysicalCenter::query()->where('is_active', true);

        if ($request->has('city')) {
            $query->where('city', $request->input('city'));
        }

        if ($request->has('state')) {
            $query->where('state', $request->input('state'));
        }

        // Support both ?service= and ?service_type= from the frontend
        $serviceFilter = $request->input('service') ?? $request->input('service_type');
        if ($serviceFilter) {
            $query->whereJsonContains('services_offered', $serviceFilter);
        }

        $centers = $query->with('services')->get();

        return $this->sendResponse($centers, 'Centers retrieved successfully.');
    }

    /**
     * Display the specified center by UUID.
     */
    public function show(string $uuid)
    {
        $center = PhysicalCenter::where('uuid', $uuid)
            ->where('is_active', true)
            ->with(['services', 'manager'])
            ->first();

        if (! $center) {
            return $this->sendError('Center not found.', [], 404);
        }

        return $this->sendResponse($center, 'Center retrieved successfully.');
    }

    /**
     * Get services offered at a specific center.
     */
    public function centerServices(string $uuid)
    {
        $center = PhysicalCenter::where('uuid', $uuid)->where('is_active', true)->first();

        if (! $center) {
            return $this->sendError('Center not found.', [], 404);
        }

        $services = CenterService::where('physical_center_id', $center->id)
            ->where('is_active', true)
            ->get();

        return $this->sendResponse($services, 'Center services retrieved successfully.');
    }

    /**
     * Get available service types from the database.
     */
    public function services()
    {
        $types = CenterService::select('service_type')
            ->distinct()
            ->pluck('service_type')
            ->map(function ($type) {
                return [
                    'id'    => $type,
                    'label' => ucwords(str_replace('_', ' ', $type)),
                    'value' => $type,
                ];
            });

        return $this->sendResponse($types, 'Service types retrieved successfully.');
    }

    /**
     * Get distinct cities that have active centers.
     */
    public function cities()
    {
        $cities = PhysicalCenter::where('is_active', true)
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->filter()
            ->values();

        return $this->sendResponse($cities, 'Cities retrieved successfully.');
    }

    /**
     * Get distinct states that have active centers.
     */
    public function states()
    {
        $states = PhysicalCenter::where('is_active', true)
            ->distinct()
            ->orderBy('state')
            ->pluck('state')
            ->filter()
            ->values();

        return $this->sendResponse($states, 'States retrieved successfully.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $_request)
    {
        return $this->sendResponse([], 'Center created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $_request, string $_id)
    {
        return $this->sendResponse([], 'Center updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $_id)
    {
        return $this->sendResponse([], 'Center deleted successfully.');
    }
}
