<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SubmitAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'responses' => 'required|array',
            'responses.*.question_id' => 'required|integer|exists:assessment_questions,id',
            'responses.*.response_value' => 'required|string',
            'responses.*.response_type' => 'required|in:text,scale,choice',
        ];
    }

    public function messages(): array
    {
        return [
            'responses.required' => 'Assessment responses are required',
            'responses.array' => 'Responses must be an array',
            'responses.*.question_id.required' => 'Question ID is required for each response',
            'responses.*.question_id.exists' => 'Invalid question ID',
            'responses.*.response_value.required' => 'Response value is required',
            'responses.*.response_type.required' => 'Response type is required',
            'responses.*.response_type.in' => 'Invalid response type',
        ];
    }
}
