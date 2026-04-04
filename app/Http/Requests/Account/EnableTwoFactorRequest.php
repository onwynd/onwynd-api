<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class EnableTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'password' => 'required|string|min:6',
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Password is required for security',
            'password.min' => 'Password must be at least 6 characters',
        ];
    }
}
