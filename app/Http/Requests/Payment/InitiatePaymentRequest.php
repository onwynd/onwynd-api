<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class InitiatePaymentRequest extends FormRequest
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
                'required',
                'numeric',
                'min:100',
                'max:100000000',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'currency' => [
                'nullable',
                'string',
                'in:NGN,naira,USD,usd,GBP,gbp,EUR,eur',
            ],
            'payment_type' => [
                'required',
                'string',
                'in:session_booking,subscription,consultation,therapist_consultation,assessment,medication,deposit',
            ],
            'description' => [
                'nullable',
                'string',
                'max:255',
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
            'metadata.*.key' => [
                'string',
                'max:50',
            ],
            'metadata.*.value' => [
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Payment amount is required',
            'amount.numeric' => 'Payment amount must be a valid number',
            'amount.min' => 'Payment amount must be at least ₦100',
            'amount.max' => 'Payment amount cannot exceed ₦100,000,000',
            'amount.regex' => 'Payment amount must have a maximum of 2 decimal places',
            'currency.in' => 'Invalid currency selected',
            'payment_type.required' => 'Payment type is required',
            'payment_type.in' => 'Invalid payment type selected',
        ];
    }

    /**
     * Get the validated input as a new collection instance.
     */
    public function getValidatedData(): array
    {
        $validated = $this->validated();

        // Normalize currency
        $currencyMap = [
            'naira' => 'NGN',
            'usd' => 'USD',
            'gbp' => 'GBP',
            'eur' => 'EUR',
        ];

        if (isset($validated['currency'])) {
            $validated['currency'] = $currencyMap[strtolower($validated['currency'])] ?? $validated['currency'];
        } else {
            $validated['currency'] = 'NGN';
        }

        // Ensure amount is a float
        $validated['amount'] = (float) $validated['amount'];

        return $validated;
    }
}
