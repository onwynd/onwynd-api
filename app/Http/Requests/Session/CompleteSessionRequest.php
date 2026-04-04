<?php

namespace App\Http\Requests\Session;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CompleteSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'session_notes' => 'nullable|string|max:2000',
            'next_session_recommendation' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'session_notes.max' => 'Session notes cannot exceed 2000 characters',
            'next_session_recommendation.max' => 'Recommendation cannot exceed 1000 characters',
        ];
    }
}
