<?php

namespace App\Http\Requests\Therapist;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->hasRole('therapist');
    }

    public function rules(): array
    {
        return [
            'full_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|regex:/^[0-9\+\-\s\(\)]+$/',
            'bio' => 'nullable|string|max:1000',
            'specialization' => 'nullable|string|max:100',
            'qualification' => 'nullable|string|max:255',
            'years_of_experience' => 'nullable|integer|min:0|max:60',
            'hourly_rate' => 'nullable|numeric|min:1000|max:100000',
            'languages' => 'nullable|array',
            'languages.*' => 'string|max:50',
            'areas_of_focus' => 'nullable|array',
            'areas_of_focus.*' => 'string|max:100',
            'avatar' => 'nullable|image|max:5120',
            'certificate' => 'nullable|mimes:pdf,jpg,jpeg,png|max:10240',
            // International / onboarding fields
            'country_of_operation'      => 'nullable|string|size:2',
            'timezone'                  => 'nullable|string|max:64',
            'payout_currency'           => 'nullable|in:NGN,USD',
            'available_for_nigeria'     => 'nullable|boolean',
            'available_for_international' => 'nullable|boolean',
            'cultural_competencies'     => 'nullable|array',
            'cultural_competencies.*'   => 'string|max:100',
            'licensing_country'         => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'hourly_rate.min' => 'Hourly rate must be at least ₦1,000',
            'hourly_rate.max' => 'Hourly rate cannot exceed ₦100,000',
            'avatar.image' => 'Avatar must be an image',
            'avatar.max' => 'Avatar cannot exceed 5MB',
            'certificate.mimes' => 'Certificate must be PDF or image file',
            'certificate.max' => 'Certificate cannot exceed 10MB',
        ];
    }
}
