<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * GetPatientDashboardRequest
 *
 * Request validation for patient dashboard endpoint
 * Validates optional filters for customized dashboard view
 */
class GetPatientDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->id == $this->route('userId');
    }

    public function rules(): array
    {
        return [
            'include_history' => 'sometimes|boolean',
            'history_days' => 'sometimes|integer|min:1|max:365',
            'include_goals' => 'sometimes|boolean',
            'include_sessions' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'history_days.min' => 'History days must be at least 1',
            'history_days.max' => 'History days cannot exceed 365',
        ];
    }

    public function getIncludeHistory(): bool
    {
        return $this->get('include_history', false);
    }

    public function getHistoryDays(): int
    {
        return $this->get('history_days', 30);
    }

    public function getIncludeGoals(): bool
    {
        return $this->get('include_goals', true);
    }

    public function getIncludeSessions(): bool
    {
        return $this->get('include_sessions', true);
    }
}
