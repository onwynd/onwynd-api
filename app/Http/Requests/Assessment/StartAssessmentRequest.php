<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StartAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'template_id' => 'required|integer|exists:assessment_templates,id',
            'session_id' => 'nullable|integer|exists:therapy_sessions,id',
        ];
    }

    public function messages(): array
    {
        return [
            'template_id.required' => 'Assessment template is required',
            'template_id.exists' => 'Selected template not found',
            'session_id.exists' => 'Selected session not found',
        ];
    }
}
