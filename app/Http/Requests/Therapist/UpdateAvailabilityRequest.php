<?php

namespace App\Http\Requests\Therapist;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return Auth::check() && $user && $user->role === 'therapist';
    }

    public function rules(): array
    {
        return [
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'is_available' => 'nullable|boolean',
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i|after:break_start',
        ];
    }

    public function messages(): array
    {
        return [
            'day_of_week.required' => 'Day of week is required',
            'day_of_week.min' => 'Invalid day of week',
            'day_of_week.max' => 'Invalid day of week',
            'start_time.required' => 'Start time is required',
            'end_time.after' => 'End time must be after start time',
            'break_end.after' => 'Break end must be after break start',
        ];
    }
}
