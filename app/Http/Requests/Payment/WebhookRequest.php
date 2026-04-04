<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class WebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Webhook authorization is handled via signature verification
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            // Paystack webhook fields
            'event' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
            'data.id' => ['nullable', 'numeric'],
            'data.amount' => ['nullable', 'numeric'],
            'data.status' => ['nullable', 'string'],
            'data.reference' => ['nullable', 'string'],
            'data.customer' => ['nullable', 'array'],
            'data.customer.email' => ['nullable', 'email'],

            // Flutterwave webhook fields
            'event' => ['nullable', 'string'],
            'data.tx_ref' => ['nullable', 'string'],
            'data.amount' => ['nullable', 'numeric'],
            'data.currency' => ['nullable', 'string'],
            'data.customer' => ['nullable', 'array'],

            // Stripe webhook fields
            'id' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'object' => ['nullable', 'string'],
        ];
    }
}
