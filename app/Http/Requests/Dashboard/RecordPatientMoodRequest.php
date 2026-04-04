<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * RecordPatientMoodRequest
 *
 * Request validation for recording patient mood check-ins
 */
class RecordPatientMoodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->id == $this->route('userId');
    }

    public function rules(): array
    {
        return [
            'mood_score' => 'required|integer|min:1|max:5',
            'note' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'mood_score.required' => 'Mood score is required',
            'mood_score.min' => 'Mood score must be between 1 and 5',
            'mood_score.max' => 'Mood score must be between 1 and 5',
        ];
    }

    public function getMoodScore(): int
    {
        return $this->get('mood_score');
    }

    public function getNote(): ?string
    {
        return $this->get('note');
    }
}
