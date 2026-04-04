<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mark Messages As Read Request
 *
 * Validates the request to mark messages as read.
 */
class MarkAsReadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'chat_ids' => ['required', 'array', 'min:1'],
            'chat_ids.*' => ['required', 'integer', 'exists:chats,id'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'chat_ids.required' => 'At least one chat message ID is required.',
            'chat_ids.array' => 'Chat IDs must be provided as an array.',
            'chat_ids.min' => 'At least one chat message ID is required.',
            'chat_ids.*.integer' => 'Each chat ID must be an integer.',
            'chat_ids.*.exists' => 'One or more chat messages do not exist.',
        ];
    }
}
