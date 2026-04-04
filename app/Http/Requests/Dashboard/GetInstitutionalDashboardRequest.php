<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * GetInstitutionalDashboardRequest
 *
 * Request validation for institutional partner dashboard
 */
class GetInstitutionalDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check if user is authorized to view this institution's dashboard
        $institutionId = $this->route('institutionId');
        $user = Auth::user();

        return $user && ($user->institution_id == $institutionId || $user->hasRole('admin'));
    }

    public function rules(): array
    {
        return [
            'include_roi' => 'sometimes|boolean',
            'include_wellness' => 'sometimes|boolean',
            'include_at_risk' => 'sometimes|boolean',
            'include_contract' => 'sometimes|boolean',
        ];
    }

    public function getIncludeROI(): bool
    {
        return $this->get('include_roi', true);
    }

    public function getIncludeWellness(): bool
    {
        return $this->get('include_wellness', true);
    }

    public function getIncludeAtRisk(): bool
    {
        return $this->get('include_at_risk', true);
    }

    public function getIncludeContract(): bool
    {
        return $this->get('include_contract', true);
    }
}
