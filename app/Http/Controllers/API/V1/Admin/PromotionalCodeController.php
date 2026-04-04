<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\PromotionalCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PromotionalCodeController extends BaseController
{
    /**
     * List all promotional codes with optional is_active filter.
     *
     * GET /api/v1/admin/promo-codes
     */
    public function index(Request $request): JsonResponse
    {
        $query = PromotionalCode::withCount('usages')->latest();

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) $request->get('per_page', 20);
        $codes   = $query->paginate($perPage);

        return $this->sendResponse($codes, 'Promotional codes retrieved successfully.');
    }

    /**
     * Create a new promotional code.
     *
     * POST /api/v1/admin/promo-codes
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'              => 'required|string|max:50|unique:promotional_codes,code',
            'description'       => 'nullable|string|max:255',
            'type'              => 'required|in:percentage,fixed',
            'discount_value'    => 'required|numeric|min:0.01',
            'currency'          => 'nullable|string|in:NGN,USD',
            'max_uses'          => 'nullable|integer|min:1',
            'max_uses_per_user' => 'nullable|integer|min:1',
            'valid_from'        => 'nullable|date',
            'valid_until'       => 'nullable|date|after:valid_from',
            'applies_to'        => 'nullable|in:session,subscription,all',
            'is_active'         => 'nullable|boolean',
        ]);

        $promoCode = PromotionalCode::create(array_merge($validated, [
            'uuid'       => (string) Str::uuid(),
            'created_by' => Auth::id(),
            'code'       => strtoupper($validated['code']),
            'applies_to' => $validated['applies_to'] ?? 'all',
            'is_active'  => $validated['is_active'] ?? true,
        ]));

        return $this->sendResponse($promoCode, 'Promotional code created successfully.', 201);
    }

    /**
     * Show a single promotional code with usage stats.
     *
     * GET /api/v1/admin/promo-codes/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $promoCode = PromotionalCode::where('uuid', $uuid)
            ->withCount('usages')
            ->with(['creator:id,full_name,email', 'usages.user:id,full_name,email'])
            ->firstOrFail();

        return $this->sendResponse($promoCode, 'Promotional code retrieved successfully.');
    }

    /**
     * Update an existing promotional code.
     *
     * PUT /api/v1/admin/promo-codes/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $promoCode = PromotionalCode::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'code'              => ['sometimes', 'required', 'string', 'max:50', Rule::unique('promotional_codes', 'code')->ignore($promoCode->id)],
            'description'       => 'nullable|string|max:255',
            'type'              => 'sometimes|required|in:percentage,fixed',
            'discount_value'    => 'sometimes|required|numeric|min:0.01',
            'currency'          => 'nullable|string|in:NGN,USD',
            'max_uses'          => 'nullable|integer|min:1',
            'max_uses_per_user' => 'nullable|integer|min:1',
            'valid_from'        => 'nullable|date',
            'valid_until'       => 'nullable|date|after:valid_from',
            'applies_to'        => 'nullable|in:session,subscription,all',
            'is_active'         => 'nullable|boolean',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $promoCode->update($validated);

        return $this->sendResponse($promoCode->fresh(), 'Promotional code updated successfully.');
    }

    /**
     * Soft-delete a promotional code.
     *
     * DELETE /api/v1/admin/promo-codes/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $promoCode = PromotionalCode::where('uuid', $uuid)->firstOrFail();
        $promoCode->delete();

        return $this->sendResponse([], 'Promotional code deleted successfully.');
    }

    /**
     * Get usage statistics for a promotional code.
     *
     * GET /api/v1/admin/promo-codes/{uuid}/stats
     */
    public function stats(string $uuid): JsonResponse
    {
        $code = PromotionalCode::where('uuid', $uuid)->firstOrFail();

        $totalUses           = $code->usages()->count();
        $uniqueUsers         = $code->usages()->distinct('user_id')->count('user_id');
        $totalDiscountGiven  = (int) $code->usages()->sum('discount_applied');
        $usesRemaining       = $code->max_uses !== null ? max(0, $code->max_uses - $totalUses) : null;

        // Revenue after discount: sum of (original amount - discount) per usage.
        // Since we only store discount_applied, we calculate from session amounts where available.
        $revenueAfterDiscount = (int) DB::table('promotional_code_usages')
            ->join('therapy_sessions', 'promotional_code_usages.session_id', '=', 'therapy_sessions.id')
            ->where('promotional_code_usages.promotional_code_id', $code->id)
            ->selectRaw('SUM(therapy_sessions.amount - promotional_code_usages.discount_applied)')
            ->value(DB::raw('SUM(therapy_sessions.amount - promotional_code_usages.discount_applied)')) ?? 0;

        $dailyUsage = DB::table('promotional_code_usages')
            ->where('promotional_code_id', $code->id)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(discount_applied) as discount_total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date'           => $row->date,
                'count'          => (int) $row->count,
                'discount_total' => (int) $row->discount_total,
            ])
            ->values()
            ->all();

        return $this->sendResponse([
            'code'                  => $code->code,
            'type'                  => $code->type,
            'value'                 => (float) $code->discount_value,
            'total_uses'            => $totalUses,
            'max_uses'              => $code->max_uses,
            'uses_remaining'        => $usesRemaining,
            'total_discount_given'  => $totalDiscountGiven,
            'unique_users'          => $uniqueUsers,
            'revenue_after_discount'=> $revenueAfterDiscount,
            'daily_usage'           => $dailyUsage,
            'is_active'             => $code->is_active,
            'expires_at'            => $code->valid_until?->toDateString(),
        ], 'Promotional code statistics retrieved successfully.');
    }

    /**
     * Toggle the is_active flag on a promotional code.
     *
     * POST /api/v1/admin/promo-codes/{uuid}/toggle
     */
    public function toggle(string $uuid): JsonResponse
    {
        $promoCode = PromotionalCode::where('uuid', $uuid)->firstOrFail();
        $promoCode->update(['is_active' => ! $promoCode->is_active]);

        $state   = $promoCode->is_active ? 'activated' : 'deactivated';
        $message = "Promotional code {$state} successfully.";

        return $this->sendResponse(['is_active' => $promoCode->is_active], $message);
    }
}
