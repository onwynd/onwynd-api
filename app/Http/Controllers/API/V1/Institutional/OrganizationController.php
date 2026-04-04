<?php

namespace App\Http\Controllers\API\V1\Institutional;

use App\Http\Controllers\API\BaseController;
use App\Models\Institutional\OrganizationMember;
use App\Models\Institutional\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationController extends BaseController
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Organization::query();

        // Role-based scoping
        if ($user->hasRole('relationship_manager')) {
            $query->where('relationship_manager_id', $user->id);
        } elseif ($user->hasRole(['admin', 'ceo', 'coo', 'sales'])) {
            // Can see all
        } else {
            // Default behavior for institutional users (see their own orgs)
            $query->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Optional filtering
        if ($request->has('manager_id')) {
            $query->where('relationship_manager_id', $request->manager_id);
        }

        $orgs = $query->paginate(20);

        return $this->sendResponse($orgs, 'Organizations retrieved.');
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->hasRole(['admin', 'sales', 'institutional', 'institution_admin', 'university_admin', 'ngo_admin'])) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:corporate,university,ngo',
            'industry' => 'nullable|string|max:150',
            'contact_email' => 'required|email',
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'total_employees' => 'nullable|integer|min:1',
            'org_type' => 'nullable|string|max:50',
            'admin_position' => 'nullable|string|max:255',
        ]);

        $org = DB::transaction(function () use ($validated, $user) {
            if ($user->hasRole(['institutional', 'institution_admin', 'university_admin', 'ngo_admin'])) {
                $existingMembership = OrganizationMember::where('user_id', $user->id)
                    ->where('role', 'admin')
                    ->first();

                if ($existingMembership) {
                    return Organization::findOrFail($existingMembership->organization_id);
                }
            }

            $organizationData = $validated;
            $organizationData['domain'] = $organizationData['domain'] ?? substr(strrchr($organizationData['contact_email'], "@"), 1) ?: null;
            unset($organizationData['admin_position']);

            $organization = Organization::create($organizationData);

            OrganizationMember::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => 'admin',
                    'department' => $validated['admin_position'] ?? null,
                ]
            );

            return $organization;
        });

        return $this->sendResponse($org, 'Organization created successfully.');
    }

    public function show(Organization $organization)
    {
        $this->authorize('view', $organization); // Assuming policy exists or logic here
        // Simple check for now
        if (! $this->canView($organization)) {
            return $this->sendError('Unauthorized', [], 403);
        }

        return $this->sendResponse($organization->load('members'), 'Organization details.');
    }

    public function update(Request $request, Organization $organization)
    {
        if (! $this->canView($organization)) { // Assuming admin of org can update
            return $this->sendError('Unauthorized', [], 403);
        }

        $organization->update($request->all());

        return $this->sendResponse($organization, 'Organization updated.');
    }

    public function getBranding(Organization $organization)
    {
        if (! $this->canView($organization)) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $branding = $organization->branding ?? ['theme' => 'default', 'font' => 'system'];

        return $this->sendResponse($branding, 'Branding retrieved.');
    }

    public function updateBranding(Request $request, Organization $organization)
    {
        if (! $this->canView($organization)) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $validated = $request->validate([
            'theme' => 'sometimes|string|in:default,onwynd',
            'font'  => 'sometimes|string|in:system,calibri',
        ]);

        $branding = array_merge($organization->branding ?? [], $validated);
        $organization->update(['branding' => $branding]);

        return $this->sendResponse($branding, 'Branding updated.');
    }

    protected function canView(Organization $organization)
    {
        $user = auth()->user();
        if ($user->hasRole('admin')) {
            return true;
        }

        return $organization->members()
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->exists();
    }
}
