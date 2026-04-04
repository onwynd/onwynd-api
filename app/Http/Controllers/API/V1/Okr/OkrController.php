<?php

namespace App\Http\Controllers\API\V1\Okr;

use App\Http\Controllers\API\BaseController;
use App\Models\Okr\OkrCheckIn;
use App\Models\Okr\OkrInitiative;
use App\Models\Okr\OkrKeyResult;
use App\Models\Okr\OkrObjective;
use App\Models\User;
use App\Services\OkrAlertService;
use App\Services\OkrProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OkrController extends BaseController
{
    /**
     * Role map â€” based on the 27 system roles.
     *
     * EXECUTIVE : full CRUD on all objectives and KRs
     * LEAD      : create/edit own KRs + initiatives; read-only on others' objectives
     * CONTRIBUTOR: check-in on own KRs/initiatives; view only
     */
    const EXECUTIVE_ROLES    = ['ceo', 'coo', 'cgo', 'admin'];
    const LEAD_ROLES         = [
        'product_manager', 'manager', 'finance', 'marketing', 'sales', 'closer',
        'hr', 'support', 'compliance', 'legal_advisor', 'clinical_advisor', 'tech_team',
    ];
    const CONTRIBUTOR_ROLES  = ['relationship_manager', 'secretary', 'employee'];

    public function __construct(
        private readonly OkrProgressService $progressService,
        private readonly OkrAlertService    $alertService,
    ) {}

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    //  READ
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * GET /api/v1/okr/objectives?quarter=Q2-2026
     *
     * Returns the full OKR tree for a quarter.
     * Executives see everything. Leads see everything read-only.
     * Contributors see only objectives/KRs they own.
     */
    public function objectives(Request $request)
    {
        $this->authorizeRoles($request, [...self::EXECUTIVE_ROLES, ...self::LEAD_ROLES, ...self::CONTRIBUTOR_ROLES]);

        $quarter = $request->get('quarter', $this->currentQuarter());
        $user    = $request->user();
        $isExec  = $this->isExecutive($user);
        $isLead  = $this->isLead($user);

        $query = OkrObjective::with([
            'owner:id,first_name,last_name',
            'keyResults.owner:id,first_name,last_name',
            'keyResults.initiatives.owner:id,first_name,last_name',
            'children.keyResults.owner:id,first_name,last_name',
        ])
        ->forQuarter($quarter)
        ->topLevel()
        ->orderBy('created_at', 'asc');

        // Contributors only see objectives/KRs they own
        if (! $isExec && ! $isLead) {
            $query->whereHas('keyResults', fn ($q) => $q->where('owner_id', $user->id));
        }

        $objectives  = $query->get()->map(fn ($obj) => $this->enrichObjective($obj, $user));
        $healthScore = $this->progressService->companyHealthScore();

        return $this->sendResponse([
            'quarter'      => $quarter,
            'health_score' => $healthScore,
            'objectives'   => $objectives,
            'user_role'    => $user->role?->slug,
            'can_manage'   => $isExec || $isLead,
        ], 'OKR objectives retrieved.');
    }

    /**
     * GET /api/v1/okr/key-results/{id}
     * Full KR detail with check-in history and initiatives.
     */
    public function showKeyResult(Request $request, int $id)
    {
        $this->authorizeRoles($request, [...self::EXECUTIVE_ROLES, ...self::LEAD_ROLES, ...self::CONTRIBUTOR_ROLES]);

        $kr = OkrKeyResult::with([
            'objective:id,title,quarter',
            'owner:id,first_name,last_name,email',
            'initiatives.owner:id,first_name,last_name',
            'checkIns' => fn ($q) => $q->latest('recorded_at')->limit(30),
            'checkIns.recorder:id,first_name,last_name',
            'alerts' => fn ($q) => $q->latest()->limit(10),
        ])->findOrFail($id);

        return $this->sendResponse([
            'key_result' => array_merge($kr->toArray(), [
                'progress' => $kr->progress,
                'pace'     => $this->progressService->calculatePace($kr),
            ]),
        ], 'Key result retrieved.');
    }

    /**
     * GET /api/v1/okr/company-health?quarter=Q2-2026
     * Overall company health score + breakdown + KRs needing attention.
     */
    public function companyHealth(Request $request)
    {
        $this->authorizeRoles($request, [...self::EXECUTIVE_ROLES, ...self::LEAD_ROLES]);

        $quarter = $request->get('quarter', $this->currentQuarter());

        $allKrs = OkrKeyResult::whereHas('objective', fn ($q) =>
            $q->where('quarter', $quarter)->where('status', 'active')
        )->get();

        $attention = OkrKeyResult::with(['objective:id,title', 'owner:id,first_name,last_name'])
            ->whereHas('objective', fn ($q) => $q->where('quarter', $quarter)->where('status', 'active'))
            ->atRiskOrOffTrack()
            ->get()
            ->map(fn ($kr) => [
                'id'        => $kr->id,
                'title'     => $kr->title,
                'health'    => $kr->health_status,
                'progress'  => $kr->progress,
                'objective' => $kr->objective?->title,
                'owner'     => $kr->owner?->first_name . ' ' . $kr->owner?->last_name,
            ]);

        return $this->sendResponse([
            'health_score'     => $this->progressService->companyHealthScore(),
            'quarter'          => $quarter,
            'breakdown'        => [
                'on_track'  => $allKrs->where('health_status', 'on_track')->count(),
                'at_risk'   => $allKrs->where('health_status', 'at_risk')->count(),
                'off_track' => $allKrs->where('health_status', 'off_track')->count(),
                'total'     => $allKrs->count(),
            ],
            'attention_needed' => $attention,
        ], 'Company OKR health retrieved.');
    }

    /**
     * GET /api/v1/okr/bindable-metrics
     * Catalogue of metric keys available for auto-binding.
     */
    public function bindableMetrics(Request $request)
    {
        $this->authorizeRoles($request, [...self::EXECUTIVE_ROLES, ...self::LEAD_ROLES]);
        return $this->sendResponse($this->progressService->bindableMetrics(), 'Bindable metrics retrieved.');
    }

    /**
     * GET /api/v1/okr/team-members
     * Users available as KR/initiative owners (executives + leads only).
     */
    public function teamMembers(Request $request)
    {
        $this->authorizeRoles($request, [...self::EXECUTIVE_ROLES, ...self::LEAD_ROLES]);

        $allRoles = array_merge(self::EXECUTIVE_ROLES, self::LEAD_ROLES, self::CONTRIBUTOR_ROLES);

        $members = User::whereHas('role', fn ($q) => $q->whereIn('slug', $allRoles))
            ->where('is_active', true)
            ->select('id', 'first_name', 'last_name', 'email')
            ->with('role:id,slug,name')
            ->orderBy('first_name')
            ->get()
            ->map(fn ($u) => [
                'id'        => $u->id,
                'name'      => trim("{$u->first_name} {$u->last_name}"),
                'email'     => $u->email,
                'role'      => $u->role?->slug,
                'role_name' => $u->role?->name,
            ]);

        return $this->sendResponse($members, 'Team members retrieved.');
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    //  CREATE
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * POST /api/v1/okr/objectives
     * Executives only.
     */
    public function storeObjective(Request $request)
    {
        $this->authorizeRoles($request, self::EXECUTIVE_ROLES);

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'quarter'     => ['required', 'string', 'regex:/^Q[1-4]-\d{4}$/'],
            'department'  => 'nullable|string|max:100',
            'parent_id'   => 'nullable|integer|exists:okr_objectives,id',
            'owner_id'    => 'nullable|integer|exists:users,id',
        ]);

        $objective = OkrObjective::create(array_merge($data, [
            'owner_id' => $data['owner_id'] ?? $request->user()->id,
            'status'   => 'active',
        ]));

        Log::info('OKR: objective created', ['id' => $objective->id, 'by' => $request->user()->id]);

        return $this->sendResponse($objective->load('owner:id,first_name,last_name'), 'Objective created.', 201);
    }

    /**
     * POST /api/v1/okr/key-results
     * Executives anywhere; leads only under objectives they own.
     */
    public function storeKeyResult(Request $request)
    {
        $this->authorizeRoles($request, [...self::EXECUTIVE_ROLES, ...self::LEAD_ROLES]);

        $data = $request->validate([
            'objective_id'  => 'required|integer|exists:okr_objectives,id',
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string|max:2000',
            'metric_key'    => 'nullable|string|max:100',
            'metric_type'   => 'required|in:auto,manual',
            'unit'          => 'nullable|string|max:20',
            'start_value'   => 'required|numeric',
            'target_value'  => 'required|numeric|different:start_value',
            'due_date'      => 'required|date|after:today',
            'owner_id'      => 'nullable|integer|exists:users,id',
        ]);

        $user = $request->user();

        // Leads can only add KRs to objectives they own
        if (! $this->isExecutive($user)) {
            $objective = OkrObjective::findOrFail($data['objective_id']);
            if ($objective->owner_id !== $user->id) {
                return $this->sendError('You can only add key results to objectives you own.', [], 403);
            }
        }

        if ($data['metric_type'] === 'auto' && empty($data['metric_key'])) {
            return $this->sendError('Auto-bound key results require a metric_key.');
        }

        $kr = OkrKeyResult::create(array_merge($data, [
            'owner_id'      => $data['owner_id'] ?? $user->id,
            'current_value' => $data['start_value'],
            'health_status' => 'on_track',
            'unit'          => $data['unit'] ?? 'count',
        ]));

        Log::info('OKR: key result created', ['id' => $kr->id, 'by' => $user->id]);

        return $this->sendResponse($kr->load('owner:id,first_name,last_name'), 'Key result created.', 201);
    }

    /**
     * POST /api/v1/okr/initiatives
     */
    public function storeInitiative(Request $request)
    {
        $this->authorizeRoles($request, [...self::EXECUTIVE_ROLES, ...self::LEAD_ROLES]);

        $data = $request->validate([
            'key_result_id' => 'required|integer|exists:okr_key_results,id',
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string|max:2000',
            'due_date'      => 'nullable|date',
            'owner_id'      => 'nullable|integer|exists:users,id',
        ]);

        $initiative = OkrInitiative::create(array_merge($data, [
            'owner_id' => $data['owner_id'] ?? $request->user()->id,
            'status'   => 'not_started',
        ]));

        return $this->sendResponse($initiative->load('owner:id,first_name,last_name'), 'Initiative created.', 201);
    }

    /**
     * POST /api/v1/okr/key-results/{id}/check-in
     * Rate-limited: 1 manual check-in per KR per user per hour.
     */
    public function checkIn(Request $request, int $id)
    {
        $this->authorizeRoles($request, [...self::EXECUTIVE_ROLES, ...self::LEAD_ROLES, ...self::CONTRIBUTOR_ROLES]);

        $data = $request->validate([
            'value' => 'required|numeric',
            'note'  => 'nullable|string|max:1000',
        ]);

        $kr   = OkrKeyResult::with(['objective', 'owner'])->findOrFail($id);
        $user = $request->user();

        // Rate limit: 1 check-in per KR per user per hour
        $tooSoon = OkrCheckIn::where('key_result_id', $kr->id)
            ->where('recorded_by', $user->id)
            ->where('recorded_at', '>=', now()->subHour())
            ->exists();

        if ($tooSoon) {
            return $this->sendError('You can only submit one check-in per key result per hour.', [], 429);
        }

        // Contributors may only check in on KRs they own
        if (! $this->isExecutive($user) && ! $this->isLead($user) && $kr->owner_id !== $user->id) {
            return $this->sendError('You can only check in on key results you own.', [], 403);
        }

        $oldHealth = $kr->health_status;
        $newHealth = $oldHealth;

        DB::transaction(function () use ($kr, $data, $user, $oldHealth, &$newHealth) {
            $kr->current_value = (float) $data['value'];
            $kr->save();

            [, $newHealth] = $this->progressService->recalculateManual($kr);

            OkrCheckIn::create([
                'key_result_id' => $kr->id,
                'value'         => (float) $data['value'],
                'note'          => $data['note'] ?? null,
                'is_automated'  => false,
                'recorded_by'   => $user->id,
                'recorded_at'   => now(),
            ]);
        });

        $kr->refresh();

        if ($oldHealth !== $newHealth) {
            $this->alertService->dispatch($kr, $oldHealth, $newHealth);
        }

        return $this->sendResponse([
            'key_result' => array_merge($kr->toArray(), [
                'progress' => $kr->progress,
                'pace'     => $this->progressService->calculatePace($kr),
            ]),
            'health_changed' => $oldHealth !== $newHealth,
        ], 'Check-in recorded.');
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    //  UPDATE
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function updateObjective(Request $request, int $id)
    {
        $this->authorizeRoles($request, self::EXECUTIVE_ROLES);

        $objective = OkrObjective::findOrFail($id);

        $objective->update($request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'status'      => 'sometimes|in:active,completed,cancelled',
            'department'  => 'nullable|string|max:100',
        ]));

        return $this->sendResponse($objective->fresh(), 'Objective updated.');
    }

    public function updateKeyResult(Request $request, int $id)
    {
        $this->authorizeRoles($request, [...self::EXECUTIVE_ROLES, ...self::LEAD_ROLES]);

        $kr   = OkrKeyResult::findOrFail($id);
        $user = $request->user();

        if (! $this->isExecutive($user) && $kr->owner_id !== $user->id) {
            return $this->sendError('You can only edit key results you own.', [], 403);
        }

        $kr->update($request->validate([
            'title'        => 'sometimes|string|max:255',
            'description'  => 'nullable|string|max:2000',
            'target_value' => 'sometimes|numeric',
            'due_date'     => 'sometimes|date',
            'metric_key'   => 'nullable|string|max:100',
            'unit'         => 'nullable|string|max:20',
        ]));

        return $this->sendResponse($kr->fresh(), 'Key result updated.');
    }

    public function updateInitiative(Request $request, int $id)
    {
        $this->authorizeRoles($request, [...self::EXECUTIVE_ROLES, ...self::LEAD_ROLES, ...self::CONTRIBUTOR_ROLES]);

        $initiative = OkrInitiative::findOrFail($id);
        $user       = $request->user();

        if (! $this->isExecutive($user) && $initiative->owner_id !== $user->id) {
            return $this->sendError('You can only edit initiatives you own.', [], 403);
        }

        $initiative->update($request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'status'      => 'sometimes|in:not_started,in_progress,completed,blocked',
            'due_date'    => 'nullable|date',
        ]));

        return $this->sendResponse($initiative->fresh(), 'Initiative updated.');
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    //  DELETE
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function destroyObjective(Request $request, int $id)
    {
        $this->authorizeRoles($request, self::EXECUTIVE_ROLES);

        OkrObjective::findOrFail($id)->delete(); // cascades: KRs â†’ initiatives â†’ check-ins â†’ alerts

        return $this->sendResponse([], 'Objective deleted.');
    }

    public function destroyKeyResult(Request $request, int $id)
    {
        $this->authorizeRoles($request, self::EXECUTIVE_ROLES);

        OkrKeyResult::findOrFail($id)->delete();

        return $this->sendResponse([], 'Key result deleted.');
    }

    public function destroyInitiative(Request $request, int $id)
    {
        $this->authorizeRoles($request, [...self::EXECUTIVE_ROLES, ...self::LEAD_ROLES]);

        $initiative = OkrInitiative::findOrFail($id);
        $user       = $request->user();

        if (! $this->isExecutive($user) && $initiative->owner_id !== $user->id) {
            return $this->sendError('You can only delete initiatives you own.', [], 403);
        }

        $initiative->delete();

        return $this->sendResponse([], 'Initiative deleted.');
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    //  HELPERS
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    private function authorizeRoles(Request $request, array $roles): void
    {
        if (! $request->user()?->hasRole($roles)) {
            abort(403, 'Insufficient permissions to access OKR data.');
        }
    }

    private function isExecutive($user): bool
    {
        return $user->hasRole(self::EXECUTIVE_ROLES);
    }

    private function isLead($user): bool
    {
        return $user->hasRole(self::LEAD_ROLES);
    }

    /** Enrich an objective with health, progress, and per-KR computed fields. */
    private function enrichObjective(OkrObjective $obj, $user): array
    {
        $krs  = $obj->keyResults;
        $data = $obj->toArray();

        $data['health']   = $obj->health;
        $data['progress'] = $obj->progress;
        $data['krs_summary'] = [
            'total'     => $krs->count(),
            'on_track'  => $krs->where('health_status', 'on_track')->count(),
            'at_risk'   => $krs->where('health_status', 'at_risk')->count(),
            'off_track' => $krs->where('health_status', 'off_track')->count(),
        ];
        $data['key_results'] = $krs->map(fn ($kr) => array_merge($kr->toArray(), [
            'progress'     => $kr->progress,
            'pace'         => $this->progressService->calculatePace($kr),
            'can_check_in' => $this->isExecutive($user) || $this->isLead($user) || $kr->owner_id === $user->id,
        ]))->values()->toArray();

        // Nest children (team objectives)
        $data['children'] = $obj->children->map(fn ($child) => $this->enrichObjective($child, $user))->values()->toArray();

        return $data;
    }

    private function currentQuarter(): string
    {
        $month = now()->month;
        $year  = now()->year;
        $q     = match (true) {
            $month <= 3  => 'Q1',
            $month <= 6  => 'Q2',
            $month <= 9  => 'Q3',
            default      => 'Q4',
        };
        return "{$q}-{$year}";
    }
}
