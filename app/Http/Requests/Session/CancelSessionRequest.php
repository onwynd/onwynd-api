<?php

namespace App\Http\Requests\Session;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CancelSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:5|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Cancellation reason is required',
            'reason.min' => 'Reason must be at least 5 characters',
            'reason.max' => 'Reason cannot exceed 500 characters',
        ];
    }
}
