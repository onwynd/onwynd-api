<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * GET /api/v1/locations
     * Search and list locations.
     *
     * Query params:
     *   - type: continent|country|state|city|lga|town|area
     *   - country_code: ISO 2-letter code (e.g. NG, GH)
     *   - parent_id: filter by parent location ID
     *   - search: name search
     *   - per_page: default 50
     */
    public function index(Request $request): JsonResponse
    {
        $query = Location::where('is_active', true);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('country_code')) {
            $query->where('country_code', strtoupper($request->country_code));
        }
        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $locations = $query->orderBy('name')
            ->paginate(min($request->input('per_page', 50), 200));

        return response()->json(['status' => 'success', 'data' => $locations]);
    }

    /**
     * GET /api/v1/locations/countries
     * All countries ordered alphabetically.
     */
    public function countries(): JsonResponse
    {
        $countries = Location::ofType('country')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'country_code', 'latitude', 'longitude', 'timezone']);

        return response()->json(['status' => 'success', 'data' => $countries]);
    }

    /**
     * GET /api/v1/locations/nigeria/states
     * All Nigerian states.
     */
    public function nigeriaStates(): JsonResponse
    {
        $nigeria = Location::ofType('country')->inCountry('NG')->first();

        $states = Location::ofType('state')
            ->where('parent_id', $nigeria?->id)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'latitude', 'longitude']);

        return response()->json(['status' => 'success', 'data' => $states]);
    }

    /**
     * GET /api/v1/locations/{id}/children
     * Children of a given location (e.g. LGAs of a state, cities of a country).
     */
    public function children(int $id): JsonResponse
    {
        $location = Location::findOrFail($id);

        $children = Location::where('parent_id', $id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type', 'latitude', 'longitude']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'parent' => $location->only(['id', 'name', 'type', 'country_code']),
                'children' => $children,
            ],
        ]);
    }
}
