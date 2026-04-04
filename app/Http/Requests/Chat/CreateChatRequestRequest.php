<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create Chat Request Validation
 *
 * Validates the request to initiate a chat with another user.
 */
class CreateChatRequestRequest extends FormRequest
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
            'to_user_id' => ['required', 'integer', 'exists:users,id', 'not_in:'.auth()->id()],
            'message' => ['required', 'string', 'max:1000', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'to_user_id.required' => 'The recipient user ID is required.',
            'to_user_id.exists' => 'The recipient user does not exist.',
            'to_user_id.not_in' => 'You cannot send a request to yourself.',
            'message.required' => 'A message is required with the chat request.',
            'message.max' => 'The message must not exceed 1000 characters.',
            'message.min' => 'The message must contain at least 1 character.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'to_user_id' => (int) $this->to_user_id,
        ]);
    }
}
