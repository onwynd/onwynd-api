<?php

namespace App\Http\Controllers\API\V1\Therapist;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends BaseController
{
    public function show(Request $request)
    {
        $user = $request->user()->load(['therapistProfile', 'role']);

        return $this->sendResponse($user, 'Profile retrieved successfully.');
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            // Therapist Profile fields
            'license_number' => 'nullable|string',
            'license_state' => 'nullable|string',
            'specializations' => 'nullable|array',
            'qualifications' => 'nullable|array',
            'languages' => 'nullable|array',
            'years_experience' => 'nullable|integer',
            'session_rate' => 'nullable|numeric',
            'bio_long' => 'nullable|string',
            'video_intro_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $user->update($request->only(['first_name', 'last_name', 'phone']));

        $profileData = $request->only([
            'license_number', 'license_state', 'specializations',
            'qualifications', 'languages', 'years_experience',
            'session_rate', 'bio_long', 'video_intro_url',
        ]);

        if (! empty($profileData)) {
            $user->therapistProfile()->updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );
        }

        return $this->sendResponse($user->load('therapistProfile'), 'Profile updated successfully.');
    }
}
