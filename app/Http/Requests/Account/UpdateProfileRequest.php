<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'full_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|regex:/^[0-9\+\-\s\(\)]+$/',
            'bio' => 'nullable|string|max:500',
            'date_of_birth' => 'nullable|date_format:Y-m-d|before:today',
            'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
            'country' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'avatar' => 'nullable|image|max:5120',
            'gravatar_style' => 'nullable|in:identicon,wavatar,retro,robohash,monsterid,mp',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'currency' => 'nullable|in:NGN,USD',
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.image' => 'Avatar must be an image',
            'avatar.max' => 'Avatar cannot exceed 5MB',
            'date_of_birth.before' => 'Date of birth must be in the past',
            'phone.regex' => 'Phone number format is invalid',
        ];
    }
}
