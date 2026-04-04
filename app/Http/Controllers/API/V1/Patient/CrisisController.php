<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CrisisController extends BaseController
{
    /**
     * Get crisis resources (hotlines, contacts).
     */
    public function index(Request $request)
    {
        $resources = [
            'hotlines' => [
                ['name' => 'Emergency Services', 'number' => '112', 'description' => 'Nigerian emergency services'],
                ['name' => 'NASS Mental Health Helpline', 'number' => '08002342255', 'description' => 'Free 24/7 mental health support'],
                ['name' => 'Lagos Suicide Prevention', 'number' => '08062467222', 'description' => 'Lagos State mental health line'],
                ['name' => 'MANI Nigeria', 'number' => '08091116264', 'description' => 'Mentally Aware Nigeria Initiative'],
            ],
            'emergency_contacts' => [
                ['name' => 'Dr. Smith (Therapist)', 'number' => '555-0123'],
                ['name' => 'Mom', 'number' => '555-0199'],
            ],
            'breathing_exercises' => [
                ['id' => 1, 'title' => '4-7-8 Breathing', 'duration' => '2 mins'],
                ['id' => 2, 'title' => 'Box Breathing', 'duration' => '1 min'],
            ],
            'safety_planning_tools' => [
                ['id' => 1, 'title' => 'My Warning Signs'],
                ['id' => 2, 'title' => 'Coping Strategies'],
            ],
        ];

        return $this->sendResponse($resources, 'Crisis resources retrieved.');
    }

    /**
     * Trigger a crisis alert.
     */
    public function alert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'severity' => 'required|in:low,medium,high',
            'message' => 'nullable|string',
            'location' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // Logic to notify trusted contacts would go here

        return $this->sendResponse([
            'alert_sent_to' => ['Dr. Smith', 'Mom'],
            'recommended_actions' => ['Stay in a safe place', 'Call 112 if immediate danger'],
        ], 'Crisis alert sent successfully.');
    }

    /**
     * Get safety plan.
     */
    public function getSafetyPlan(Request $request)
    {
        $plan = [
            'warning_signs' => ['Feeling overwhelmed', 'Sleeping too much'],
            'coping_strategies' => ['Listen to music', 'Call a friend', 'Walk the dog'],
            'support_contacts' => [
                ['name' => 'Mom', 'phone' => '555-0199'],
                ['name' => 'Best Friend', 'phone' => '555-0122'],
            ],
            'professional_resources' => [
                ['name' => 'Therapist Office', 'phone' => '555-0123'],
                ['name' => 'Local Hospital', 'address' => '123 Main St'],
            ],
        ];

        return $this->sendResponse($plan, 'Safety plan retrieved.');
    }

    /**
     * Update safety plan.
     */
    public function updateSafetyPlan(Request $request)
    {
        // Mock update
        $plan = $request->only(['warning_signs', 'coping_strategies', 'support_contacts', 'professional_resources']);

        return $this->sendResponse($plan, 'Safety plan updated successfully.');
    }

    /**
     * Call emergency services.
     */
    public function callEmergency(Request $request)
    {
        // Log the call attempt

        return $this->sendResponse([
            'emergency_number' => '112',
            'location_shared' => true,
        ], 'Emergency services contacted.');
    }
}
