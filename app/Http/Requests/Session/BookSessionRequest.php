<?php

namespace App\Http\Requests\Session;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class BookSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Allow anonymous bookings or authenticated users
        return Auth::check() || $this->input('is_anonymous') === true;
    }

    public function rules(): array
    {
        return [
            // Legacy booking fields
            'therapist_id' => 'nullable|integer|exists:therapist_profiles,id',
            'session_date' => 'nullable|date_format:Y-m-d|after_or_equal:today',
            'session_time' => 'nullable|date_format:H:i',

            // Frontend-aligned booking fields
            'therapist_uuid' => 'nullable|string|exists:users,uuid',
            'scheduled_at' => 'nullable|date|after_or_equal:now',
            'session_type' => 'nullable|in:consultation,follow_up,intensive,video,audio,chat',
            'duration_minutes' => 'nullable|integer|in:30,45,60,90,120',
            'notes' => 'nullable|string|max:1000',
            'participants' => 'nullable|array',
            'participants.*' => 'exists:users,id',

            // Promotional code
            'promo_code' => 'nullable|string|max:50',

            // Anonymous booking fields
            'is_anonymous' => 'nullable|boolean',
            'anonymous_nickname' => 'nullable|string|max:50|required_if:is_anonymous,true',
            'anonymous_email' => 'nullable|email|required_if:is_anonymous,true',
            'payment_name' => 'nullable|string|max:100|required_if:is_anonymous,true',
        ];
    }

    public function messages(): array
    {
        return [
            'therapist_id.exists' => 'Selected therapist not found',
            'therapist_uuid.exists' => 'Selected therapist not found',
            'session_date.after_or_equal' => 'Session date must be today or later',
            'duration_minutes.in' => 'Duration must be 30, 45, 60, 90, or 120 minutes',
        ];
    }
}
