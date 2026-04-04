<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingsController extends BaseController
{
    /**
     * Get app settings.
     */
    public function index(Request $request)
    {
        $settings = [
            'theme' => 'light',
            'language' => 'en',
            'notifications' => [
                'push_enabled' => true,
                'email_enabled' => false,
                'reminders' => true,
                'community_updates' => true,
            ],
            'privacy' => [
                'profile_visibility' => 'private',
                'data_sharing' => false,
            ],
            'accessibility' => [
                'font_size' => 'medium',
                'high_contrast' => false,
            ],
        ];

        return $this->sendResponse($settings, 'App settings retrieved.');
    }

    /**
     * Update app settings.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'theme' => 'nullable|string|in:light,dark,system',
            'language' => 'nullable|string|size:2',
            'notifications' => 'nullable|array',
            'privacy' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $settings = $request->all();

        return $this->sendResponse($settings, 'App settings updated successfully.');
    }
}
