<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitGuestAssessmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Allow unauthenticated users to submit guest assessments
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assessment_uuid' => 'required|string|exists:assessments,uuid',
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => 'required|integer|exists:assessment_questions,id',
            'answers.*.score' => 'required|integer|between:0,3',
            'answers.*.answer' => 'required|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'assessment_uuid.required' => 'Assessment UUID is required',
            'assessment_uuid.exists' => 'Invalid assessment',
            'answers.required' => 'Assessment answers are required',
            'answers.array' => 'Answers must be an array',
            'answers.min' => 'At least one answer is required',
            'answers.*.question_id.required' => 'Question ID is required for each answer',
            'answers.*.question_id.exists' => 'Invalid question ID',
            'answers.*.score.required' => 'Score is required for each answer',
            'answers.*.score.between' => 'Score must be between 0 and 3',
            'answers.*.answer.required' => 'Answer text is required',
            'answers.*.answer.max' => 'Answer text must not exceed 500 characters',
        ];
    }
}
