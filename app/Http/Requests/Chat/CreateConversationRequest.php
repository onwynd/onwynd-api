<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'recipient_id' => 'required_without:assessment_result_ids|integer|exists:users,id|not_in:'.Auth::id(),
            'assessment_result_ids' => 'required_without:recipient_id|array',
            'assessment_result_ids.*' => 'integer|exists:user_assessment_results,id',
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_id.required_without' => 'Recipient is required when no assessment context is provided',
            'recipient_id.exists' => 'Recipient user not found',
            'recipient_id.not_in' => 'Cannot start conversation with yourself',
            'assessment_result_ids.required_without' => 'Select at least one assessment to include',
        ];
    }
}
