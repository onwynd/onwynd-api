<?php

namespace App\Http\Requests\Session;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RescheduleSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'new_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'new_time' => 'required|date_format:H:i',
        ];
    }

    public function messages(): array
    {
        return [
            'new_date.required' => 'New session date is required',
            'new_date.after_or_equal' => 'New date must be today or later',
            'new_time.required' => 'New session time is required',
            'new_time.date_format' => 'Time must be in HH:MM format',
        ];
    }
}
