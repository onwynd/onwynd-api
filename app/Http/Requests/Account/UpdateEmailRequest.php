<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'new_email' => 'required|email|unique:users,email|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'new_email.required' => 'New email is required',
            'new_email.email' => 'Email must be valid',
            'new_email.unique' => 'Email already in use',
            'new_email.max' => 'Email cannot exceed 255 characters',
        ];
    }
}
