<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * GetTherapistDashboardRequest
 *
 * Request validation for therapist dashboard endpoint
 */
class GetTherapistDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->id == $this->route('therapistId');
    }

    public function rules(): array
    {
        return [
            'include_reviews' => 'sometimes|boolean',
            'include_earnings_detail' => 'sometimes|boolean',
            'include_patients' => 'sometimes|boolean',
        ];
    }

    public function getIncludeReviews(): bool
    {
        return $this->get('include_reviews', true);
    }

    public function getIncludeEarningsDetail(): bool
    {
        return $this->get('include_earnings_detail', false);
    }

    public function getIncludePatients(): bool
    {
        return $this->get('include_patients', false);
    }
}
