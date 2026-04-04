<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RefundPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'amount' => [
                'nullable',
                'numeric',
                'min:1',
                'max:100000000',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'reason' => [
                'nullable',
                'string',
                'in:customer_request,payment_error,cancellation,duplicate_payment,other',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'amount.numeric' => 'Refund amount must be a valid number',
            'amount.min' => 'Refund amount must be at least ₦1',
            'amount.max' => 'Refund amount cannot exceed ₦100,000,000',
            'amount.regex' => 'Refund amount must have a maximum of 2 decimal places',
            'reason.in' => 'Invalid refund reason selected',
        ];
    }

    /**
     * Get the validated input as a new collection instance.
     */
    public function getValidatedData(): array
    {
        $validated = $this->validated();

        if (isset($validated['amount'])) {
            $validated['amount'] = (float) $validated['amount'];
        }

        return $validated;
    }
}
