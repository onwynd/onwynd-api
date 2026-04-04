<?php

namespace App\Http\Controllers\API\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\SalesTerritory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerritoryController extends Controller
{
    /**
     * GET /api/v1/sales/territories
     * List territories (supports nesting and filter by type).
     */
    public function index(Request $request): JsonResponse
    {
        $query = SalesTerritory::with('children')
            ->where('is_active', true);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        } elseif (! $request->boolean('all')) {
            // Default: return root territories
            $query->whereNull('parent_id');
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get(),
        ]);
    }

    /**
     * POST /api/v1/admin/sales/territories
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:sales_territories,code',
            'type' => 'required|in:region,zone,state,city,lga,area,school,custom',
            'parent_id' => 'nullable|exists:sales_territories,id',
            'country' => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);

        $territory = SalesTerritory::create($data);

        return response()->json(['status' => 'success', 'data' => $territory], 201);
    }

    /**
     * GET /api/v1/admin/sales/territories/{id}
     */
    public function show(SalesTerritory $territory): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $territory->load(['parent', 'children', 'agents']),
        ]);
    }

    /**
     * PUT /api/v1/admin/sales/territories/{id}
     */
    public function update(Request $request, SalesTerritory $territory): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|nullable|string|max:50|unique:sales_territories,code,'.$territory->id,
            'type' => 'sometimes|in:region,zone,state,city,lga,area,school,custom',
            'parent_id' => 'sometimes|nullable|exists:sales_territories,id',
            'country' => 'sometimes|nullable|string|max:100',
            'description' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $territory->update($data);

        return response()->json(['status' => 'success', 'data' => $territory]);
    }

    /**
     * DELETE /api/v1/admin/sales/territories/{id}
     */
    public function destroy(SalesTerritory $territory): JsonResponse
    {
        $territory->update(['is_active' => false]);

        return response()->json(['status' => 'success', 'message' => 'Territory deactivated.']);
    }

    /**
     * POST /api/v1/admin/sales/territories/{id}/assign
     * Assign agents to a territory with specific roles.
     */
    public function assign(Request $request, SalesTerritory $territory): JsonResponse
    {
        $data = $request->validate([
            'assignments' => 'required|array|min:1',
            'assignments.*.user_id' => 'required|exists:users,id',
            'assignments.*.role' => 'required|in:zone_manager,regional_manager,city_agent,school_agent,area_agent,lga_agent,territory_lead,sales_rep',
            'assignments.*.is_primary' => 'sometimes|boolean',
        ]);

        foreach ($data['assignments'] as $assignment) {
            $territory->agents()->syncWithoutDetaching([
                $assignment['user_id'] => [
                    'role' => $assignment['role'],
                    'is_primary' => $assignment['is_primary'] ?? false,
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Agents assigned to territory.',
            'data' => $territory->load('agents'),
        ]);
    }

    /**
     * DELETE /api/v1/admin/sales/territories/{id}/agents/{userId}
     * Remove an agent from a territory.
     */
    public function removeAgent(SalesTerritory $territory, int $userId): JsonResponse
    {
        $territory->agents()->detach($userId);

        return response()->json(['status' => 'success', 'message' => 'Agent removed from territory.']);
    }

    /**
     * GET /api/v1/sales/my-territories
     * Return the authenticated agent's territories.
     */
    public function myTerritories(Request $request): JsonResponse
    {
        $user = $request->user();

        $territories = SalesTerritory::whereHas('agents', fn ($q) => $q->where('users.id', $user->id))
            ->with(['parent', 'children'])
            ->get()
            ->map(function ($t) use ($user) {
                $pivot = $t->agents->firstWhere('id', $user->id)?->pivot;
                $t->my_role = $pivot?->role;
                $t->is_primary = $pivot?->is_primary;
                unset($t->agents);

                return $t;
            });

        return response()->json(['status' => 'success', 'data' => $territories]);
    }
}
