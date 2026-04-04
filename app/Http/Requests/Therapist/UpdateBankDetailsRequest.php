<?php

namespace App\Http\Requests\Therapist;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateBankDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return Auth::check() && $user && $user->role === 'therapist';
    }

    public function rules(): array
    {
        return [
            'account_holder' => 'required|string|max:255',
            'account_number' => 'required|string|regex:/^[0-9]{10}$/',
            'bank_code' => 'required|string|max:10',
            'bank_name' => 'required|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'account_holder.required' => 'Account holder name is required',
            'account_number.required' => 'Account number is required',
            'account_number.regex' => 'Account number must be 10 digits',
            'bank_code.required' => 'Bank code is required',
            'bank_name.required' => 'Bank name is required',
        ];
    }
}
