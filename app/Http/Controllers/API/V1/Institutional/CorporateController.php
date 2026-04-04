<?php

namespace App\Http\Controllers\API\V1\Institutional;

use App\Http\Controllers\API\BaseController;
use App\Mail\Corporate\PilotActivatedEmail;
use App\Models\InstitutionalContract;
use App\Models\Institutional\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CorporateController extends BaseController
{
    /**
     * Public corporate pricing tiers (no auth required).
     */
    public function pricing()
    {
        $tiers = [
            [
                'id' => 'starter',
                'name' => 'Starter',
                'seats' => '5–20',
                'min_seats' => 5,
                'max_seats' => 20,
                'monthly' => 15000,
                'monthly_max' => 20000,   // range: ₦15,000 – ₦20,000
                'annual' => 13000,
                'annual_max' => 17000,
                'highlight' => false,
                'sessions_per_month' => 0,
                'session_duration_minutes' => null,
                'session_subsidy_pct' => 0,
                'session_ceiling_ngn' => 0,
                'features' => [
                    'Up to 20 employees',
                    'Unlimited AI companion',
                    'Unlimited group sessions',
                    'PHQ-9 & GAD-7 assessments',
                    'HR analytics dashboard',
                    'Individual therapy: pay-per-session',
                    'Email support',
                ],
                'currency' => 'NGN',
            ],
            [
                'id' => 'growth',
                'name' => 'Growth',
                'seats' => '21–100',
                'min_seats' => 21,
                'max_seats' => 100,
                'monthly' => 24000,
                'monthly_max' => null,
                'annual' => 20000,
                'annual_max' => null,
                'highlight' => true,
                'sessions_per_month' => 3,
                'session_duration_minutes' => 35,
                'session_subsidy_pct' => 100,
                'session_ceiling_ngn' => 15000,
                'features' => [
                    'Up to 100 employees',
                    'Everything in Starter',
                    '3 × 35-min therapy sessions/month (pool)',
                    'Sessions covered up to ₦15,000 each',
                    'Dedicated wellness manager',
                    'Priority support',
                ],
                'currency' => 'NGN',
            ],
            [
                'id' => 'enterprise',
                'name' => 'Enterprise',
                'seats' => '100+',
                'min_seats' => 100,
                'max_seats' => null,
                'monthly' => 0,       // 0 = custom / contact us
                'monthly_max' => null,
                'annual' => 0,
                'annual_max' => null,
                'highlight' => false,
                'sessions_per_month' => null,    // negotiated per contract
                'session_duration_minutes' => null,
                'session_subsidy_pct' => 100,
                'session_ceiling_ngn' => null,    // negotiated
                'features' => [
                    'Unlimited employees',
                    'Everything in Growth',
                    'Custom session allocation',
                    'Custom therapy programmes',
                    'Dedicated account manager',
                    'SLA & compliance reporting',
                ],
                'currency' => 'NGN',
            ],
        ];

        return $this->sendResponse([
            'tiers' => $tiers,
            'currency' => 'NGN',
            'savings_percentage' => 15,
        ], 'Corporate pricing retrieved successfully.');
    }

    /**
     * Display a listing of corporate organizations.
     */
    public function index(Request $request)
    {
        // Admin sees all corporates
        if ($request->user()->hasRole('admin')) {
            $orgs = Organization::where('type', 'corporate')->paginate(20);
        } else {
            // User sees their own corporate orgs where they are admin/member
            $orgs = Organization::where('type', 'corporate')
                ->whereHas('members', function ($q) use ($request) {
                    $q->where('user_id', $request->user()->id);
                })->paginate(20);
        }

        return $this->sendResponse($orgs, 'Corporate organizations retrieved successfully.');
    }

    /**
     * Store a newly created corporate organization.
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

        $validated['type'] = 'corporate';

        $org = Organization::create($validated);

        return $this->sendResponse($org, 'Corporate organization created successfully.', 201);
    }

    /**
     * Display the specified corporate organization.
     */
    public function show(Organization $corporate)
    {
        if ($corporate->type !== 'corporate') {
            return $this->sendError('Organization is not a corporate entity.', [], 404);
        }

        if (! $this->canAccess($corporate)) {
            return $this->sendError('Unauthorized', [], 403);
        }

        return $this->sendResponse($corporate->load('members'), 'Corporate organization details retrieved.');
    }

    /**
     * Update the specified corporate organization.
     */
    public function update(Request $request, Organization $corporate)
    {
        if ($corporate->type !== 'corporate') {
            return $this->sendError('Organization is not a corporate entity.', [], 404);
        }

        if (! $this->canManage($corporate)) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $validated = $request->validate([
            'name' => 'string|max:255',
            'contact_email' => 'email',
            'domain' => 'nullable|string',
            'max_members' => 'integer',
        ]);

        $corporate->update($validated);

        return $this->sendResponse($corporate, 'Corporate organization updated successfully.');
    }

    /**
     * Remove the specified corporate organization.
     */
    public function destroy(Organization $corporate)
    {
        if ($corporate->type !== 'corporate') {
            return $this->sendError('Organization is not a corporate entity.', [], 404);
        }

        if (! auth()->user()->hasRole('admin')) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $corporate->delete();

        return $this->sendResponse(null, 'Corporate organization deleted successfully.');
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
     * POST /api/v1/institutional/corporates/{corporate}/pilot/activate
     * Mark a corporate contract as active (status = 'active') and send the
     * HR director a PilotActivatedEmail.
     *
     * Expected request body:
     *   hr_email       string  required  HR director email
     *   hr_name        string  required  HR director name
     *   contract_id    int     required  ID of the InstitutionalContract row
     */
    public function activatePilot(Request $request, Organization $corporate): JsonResponse
    {
        if ($corporate->type !== 'corporate') {
            return $this->sendError('Organization is not a corporate entity.', [], 404);
        }

        if (! $this->canManage($corporate)) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $validated = $request->validate([
            'hr_email'    => 'required|email',
            'hr_name'     => 'required|string|max:255',
            'contract_id' => 'required|integer|exists:institutional_contracts,id',
        ]);

        $contract = InstitutionalContract::findOrFail($validated['contract_id']);

        if ($contract->institution_user_id !== null) {
            // Ensure contract belongs to a user linked to this org (best-effort guard).
            $orgMemberUserIds = $corporate->members()->pluck('user_id')->toArray();
            if (! in_array($contract->institution_user_id, $orgMemberUserIds, true)) {
                return $this->sendError('Contract does not belong to this organization.', [], 403);
            }
        }

        // Mark contract active.
        $contract->update(['status' => 'active']);

        $pilotStart   = $contract->start_date ? \Carbon\Carbon::parse($contract->start_date) : now();
        $pilotEnd     = $contract->end_date   ? \Carbon\Carbon::parse($contract->end_date)   : now()->addMonths(3);
        $sessionQuota = (int) ($contract->total_sessions_quota ?? 0);
        $currency     = $corporate->country === 'NG' ? 'NGN' : 'NGN';

        Mail::to($validated['hr_email'])->queue(new PilotActivatedEmail(
            orgName:      $corporate->name,
            hrName:       $validated['hr_name'],
            pilotStart:   $pilotStart,
            pilotEnd:     $pilotEnd,
            sessionQuota: $sessionQuota,
            currency:     $currency,
            sessionFee:   (float) ($contract->contract_value ?? 0),
            bookingFee:   0.0,
        ));

        Log::info('Corporate pilot activated and email queued', [
            'org_id'      => $corporate->id,
            'contract_id' => $contract->id,
            'hr_email'    => $validated['hr_email'],
        ]);

        return $this->sendResponse([
            'contract_id'   => $contract->id,
            'status'        => 'active',
            'pilot_start'   => $pilotStart->toDateString(),
            'pilot_end'     => $pilotEnd->toDateString(),
            'session_quota' => $sessionQuota,
        ], 'Pilot activated. Confirmation email queued for HR director.');
    }

    /**
     * POST /api/v1/institutional/corporates/import
     * Bulk-import corporate organizations from a CSV file.
     * Expected columns: name, email, phone, sector, max_seats
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $header = array_map('strtolower', array_map('trim', fgetcsv($handle)));

        $created = 0;
        $skipped = 0;
        $errors  = [];

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < count($header)) { $skipped++; continue; }
                $data = array_combine($header, $row);

                $name = trim($data['name'] ?? '');
                if (! $name) { $skipped++; continue; }

                if (Organization::where('name', $name)->where('org_type', 'corporate')->exists()) {
                    $skipped++;
                    continue;
                }

                Organization::create([
                    'name'       => $name,
                    'email'      => trim($data['email'] ?? '') ?: null,
                    'phone'      => trim($data['phone'] ?? '') ?: null,
                    'sector'     => trim($data['sector'] ?? '') ?: null,
                    'max_seats'  => (int) ($data['max_seats'] ?? 10),
                    'org_type'   => 'corporate',
                    'is_active'  => true,
                ]);
                $created++;
            }
            fclose($handle);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Corporate CSV import failed', ['error' => $e->getMessage()]);
            return $this->sendError('Import failed: ' . $e->getMessage(), 500);
        }

        return $this->sendResponse([
            'created' => $created,
            'skipped' => $skipped,
        ], "Import complete. {$created} organizations created, {$skipped} skipped.");
    }
}
