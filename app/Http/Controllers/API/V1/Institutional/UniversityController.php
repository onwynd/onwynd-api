<?php

namespace App\Http\Controllers\API\V1\Institutional;

use App\Http\Controllers\API\BaseController;
use App\Models\Institutional\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UniversityController extends BaseController
{
    /**
     * Display a listing of university organizations.
     */
    public function index(Request $request)
    {
        $query = Organization::whereIn('type', ['university', 'corporate'])
            ->withCount('members as onboarded_count');

        // Non-admins only see orgs they belong to
        if (! $request->user()->hasRole('admin')) {
            $query->whereHas('members', fn ($q) => $q->where('user_id', $request->user()->id));
        }

        // Filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('country')) {
            $query->where('country', $request->country);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('contact_email', 'like', "%{$s}%")
                ->orWhere('city', 'like', "%{$s}%")
            );
        }

        $perPage = $request->integer('per_page', 100); // large default so all seeded records show
        $orgs    = $query->orderBy('name')->paginate($perPage);

        // Also return distinct countries for filter dropdown
        $countries = Organization::whereIn('type', ['university', 'corporate'])
            ->whereNotNull('country')
            ->distinct()
            ->pluck('country')
            ->sort()
            ->values();

        return $this->sendResponse([
            'organizations' => $orgs->items(),
            'pagination'    => [
                'total'        => $orgs->total(),
                'per_page'     => $orgs->perPage(),
                'current_page' => $orgs->currentPage(),
                'last_page'    => $orgs->lastPage(),
            ],
            'countries' => $countries,
        ], 'Organizations retrieved successfully.');
    }

    /**
     * Store a newly created university organization.
     */
    public function store(Request $request)
    {
        if (! $request->user()->hasRole(['admin', 'sales'])) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_email' => 'required|email',
            'domain' => 'nullable|string|max:255',
            'max_members' => 'integer|min:1',
        ]);

        $validated['type'] = 'university';

        $org = Organization::create($validated);

        return $this->sendResponse($org, 'University organization created successfully.', 201);
    }

    /**
     * Display the specified university organization.
     */
    public function show(Organization $university)
    {
        if ($university->type !== 'university') {
            return $this->sendError('Organization is not a university entity.', [], 404);
        }

        if (! $this->canAccess($university)) {
            return $this->sendError('Unauthorized', [], 403);
        }

        return $this->sendResponse($university->load('members'), 'University organization details retrieved.');
    }

    /**
     * Update the specified university organization.
     */
    public function update(Request $request, Organization $university)
    {
        if ($university->type !== 'university') {
            return $this->sendError('Organization is not a university entity.', [], 404);
        }

        if (! $this->canManage($university)) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $validated = $request->validate([
            'name' => 'string|max:255',
            'contact_email' => 'email',
            'domain' => 'nullable|string',
            'max_members' => 'integer',
        ]);

        $university->update($validated);

        return $this->sendResponse($university, 'University organization updated successfully.');
    }

    /**
     * Remove the specified university organization.
     */
    public function destroy(Organization $university)
    {
        if ($university->type !== 'university') {
            return $this->sendError('Organization is not a university entity.', [], 404);
        }

        if (! auth()->user()->hasRole('admin')) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $university->delete();

        return $this->sendResponse(null, 'University organization deleted successfully.');
    }

    protected function canAccess(Organization $org)
    {
        $user = auth()->user();
        if ($user->hasRole('admin')) {
            return true;
        }

        return $org->members()->where('user_id', $user->id)->exists();
    }

    protected function canManage(Organization $org)
    {
        $user = auth()->user();
        if ($user->hasRole('admin')) {
            return true;
        }

        return $org->members()->where('user_id', $user->id)->where('role', 'admin')->exists();
    }

    /**
     * POST /api/v1/institutional/universities/import
     * Bulk-import universities from CSV. Columns: name, email, phone, state, max_seats
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map('strtolower', array_map('trim', fgetcsv($handle)));

        $created = 0;
        $skipped = 0;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < count($header)) { $skipped++; continue; }
                $data = array_combine($header, $row);
                $name = trim($data['name'] ?? '');
                if (!$name) { $skipped++; continue; }

                if (Organization::where('name', $name)->where('org_type', 'university')->exists()) {
                    $skipped++; continue;
                }

                Organization::create([
                    'name'      => $name,
                    'email'     => trim($data['email'] ?? '') ?: null,
                    'phone'     => trim($data['phone'] ?? '') ?: null,
                    'state'     => trim($data['state'] ?? '') ?: null,
                    'max_seats' => (int) ($data['max_seats'] ?? 50),
                    'org_type'  => 'university',
                    'is_active' => true,
                ]);
                $created++;
            }
            fclose($handle);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('University CSV import failed', ['error' => $e->getMessage()]);
            return $this->sendError('Import failed: ' . $e->getMessage(), 500);
        }

        return $this->sendResponse(
            ['created' => $created, 'skipped' => $skipped],
            "{$created} universities created, {$skipped} skipped."
        );
    }
}
