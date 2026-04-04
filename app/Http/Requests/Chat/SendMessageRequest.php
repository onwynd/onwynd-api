<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:5000',
            'message_type' => 'nullable|in:text,image,file,video',
            'attachments' => 'nullable|array',
            'attachments.*' => 'string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Message content is required',
            'content.max' => 'Message cannot exceed 5000 characters',
            'message_type.in' => 'Invalid message type',
            'attachments.array' => 'Attachments must be an array',
        ];
    }
}
