<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Institutional\Organization;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UniversityConfigController extends BaseController
{
    /**
     * Get university configuration for an organization (admin-only).
     */
    public function show(Organization $organization)
    {
        if ($organization->type !== 'university') {
            return $this->sendError('Organization is not a university.', [], 422);
        }

        $data = $organization->only([
            'id',
            'name',
            'type',
            'funding_model',
            'billing_cycle',
            'semester_start_month',
            'semester_2_start_month',
            'session_credits_per_student',
            'session_ceiling_ngn',
            'domain_auto_join',
            'university_domain',
            'student_id_verification',
            'crisis_notification_email',
            'early_crisis_detection',
        ]);

        return $this->sendResponse($data, 'University configuration retrieved.');
    }

    /**
     * Update university configuration (admin-only).
     */
    public function update(Request $request, Organization $organization)
    {
        if ($organization->type !== 'university') {
            return $this->sendError('Organization is not a university.', [], 422);
        }

        $validated = $request->validate([
            'funding_model' => ['nullable', Rule::in(['model_a', 'model_b'])],
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'semester', 'annual'])],
            'semester_start_month' => ['nullable', 'integer', 'between:1,12'],
            'semester_2_start_month' => ['nullable', 'integer', 'between:1,12'],
            'session_credits_per_student' => ['nullable', 'integer', 'min:0', 'max:12'],
            'session_ceiling_ngn' => ['nullable', 'integer', 'min:0'],
            'domain_auto_join' => ['nullable', 'boolean'],
            'university_domain' => ['nullable', 'string'],
            'student_id_verification' => ['nullable', 'boolean'],
            'crisis_notification_email' => ['nullable', 'email'],
            // early_crisis_detection is always true for universities; do not allow disabling
            'early_crisis_detection' => ['sometimes', 'accepted'],
        ]);

        // Enforce early crisis detection always on for universities
        $validated['early_crisis_detection'] = true;

        // If domain_auto_join is false, clear university_domain to avoid stale state
        if (array_key_exists('domain_auto_join', $validated) && ! $validated['domain_auto_join']) {
            $validated['university_domain'] = null;
        }

        $organization->fill($validated);

        // Defaults based on funding model if not provided
        if (empty($organization->billing_cycle)) {
            if ($organization->funding_model === 'model_a') {
                $organization->billing_cycle = 'annual';
            } elseif ($organization->funding_model === 'model_b') {
                $organization->billing_cycle = 'semester';
            }
        }

        $organization->save();

        $data = $organization->only([
            'id',
            'name',
            'type',
            'funding_model',
            'billing_cycle',
            'semester_start_month',
            'semester_2_start_month',
            'session_credits_per_student',
            'session_ceiling_ngn',
            'domain_auto_join',
            'university_domain',
            'student_id_verification',
            'crisis_notification_email',
            'early_crisis_detection',
        ]);

        return $this->sendResponse($data, 'University configuration updated.');
    }
}
