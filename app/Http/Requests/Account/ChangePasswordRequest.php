<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|string|min:6',
            'new_password' => 'required|string|min:8|different:current_password|regex:/[a-z]/'.
                            '|regex:/[A-Z]/'.
                            '|regex:/[0-9]/'.
                            '|regex:/[!@#$%^&*]/'.
                            '|confirmed',
            'new_password_confirmation' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Current password is required',
            'new_password.required' => 'New password is required',
            'new_password.min' => 'Password must be at least 8 characters',
            'new_password.different' => 'New password must be different from your current password',
            'new_password.regex' => 'Password must contain uppercase, lowercase, number, and special character',
            'new_password.confirmed' => 'Passwords do not match',
            'new_password_confirmation.required' => 'Password confirmation is required',
        ];
    }
}
