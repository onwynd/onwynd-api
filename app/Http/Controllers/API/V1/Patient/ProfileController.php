<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Mail\PasswordChanged;
use App\Models\Institutional\OrganizationMember;
use App\Models\User;
use App\Notifications\WelcomePatient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ProfileController extends BaseController
{
    public function show(Request $request)
    {
        $user = $request->user()->load(['patient', 'role']);

        // Append corporate/institutional membership if the user belongs to an org
        $membership = OrganizationMember::with('organization')
            ->where('user_id', $user->id)
            ->where('role', 'member')
            ->first();

        $organizationMembership = null;

        if ($membership && $membership->organization) {
            $org = $membership->organization;

            $planConfig = $this->planSessionConfig($org->subscription_plan);

            $sessionsUsed = $membership->sessions_used_this_month ?? 0;
            $sessionsLimit = $membership->sessions_limit ?? $planConfig['sessions_per_month'];
            $sessionsRemaining = $sessionsLimit > 0 ? max(0, $sessionsLimit - $sessionsUsed) : 0;

            $resetCadence = ($org->billing_cycle === 'semester') ? 'semester' : 'monthly';
            $nextResetAt = null;
            if ($resetCadence === 'semester') {
                $m1 = (int) ($org->semester_start_month ?? 1);
                $m2 = (int) ($org->semester_2_start_month ?? 7);
                $now = Carbon::now();
                $candidates = [
                    Carbon::create($now->year, $m1, 1)->startOfDay(),
                    Carbon::create($now->year, $m2, 1)->startOfDay(),
                ];
                $future = collect($candidates)->first(function (Carbon $d) use ($now) {
                    return $d->greaterThan($now);
                });
                if ($future) {
                    $nextResetAt = $future;
                } else {
                    $nextResetAt = Carbon::create($now->year + 1, $m1, 1)->startOfDay();
                }
            } else {
                $now = Carbon::now();
                $nextResetAt = $now->copy()->startOfMonth()->addMonth()->startOfDay();
            }

            $organizationMembership = [
                'status' => $org->status ?? 'active',
                'organization_name' => $org->name,
                'organization_type' => $org->type,
                'plan_tier' => strtolower($org->subscription_plan ?? 'starter'),
                'sessions_per_month' => $planConfig['sessions_per_month'],
                'session_duration_minutes' => $planConfig['session_duration_minutes'],
                'session_ceiling_ngn' => $planConfig['session_ceiling_ngn'],
                'sessions_used_this_month' => $sessionsUsed,
                'sessions_remaining' => $sessionsRemaining,
                'last_reset_at' => $membership->last_reset_at ?? null,
                'reset_cadence' => $resetCadence,
                'next_reset_at' => $nextResetAt ? $nextResetAt->toIso8601String() : null,
            ];
        }

        $data = $user->toArray();
        $data['organization_membership'] = $organizationMembership;

        return $this->sendResponse($data, 'Profile retrieved successfully.');
    }

    /**
     * Return session config defaults for a given plan tier string.
     * Enterprise values are set per-org on organization_members.sessions_limit.
     */
    private function planSessionConfig(string $plan): array
    {
        return match (strtolower($plan)) {
            'growth' => ['sessions_per_month' => 3,    'session_duration_minutes' => 35, 'session_ceiling_ngn' => 15000],
            'enterprise' => ['sessions_per_month' => null, 'session_duration_minutes' => null, 'session_ceiling_ngn' => null],
            default => ['sessions_per_month' => 0,    'session_duration_minutes' => null, 'session_ceiling_ngn' => 0],
        };
    }

    public function setup(Request $request)
    {
        $user = $request->user();
        $isFirstTime = $user->onboarding_completed_at === null;

        $response = $this->update($request);

        if ($isFirstTime && $response->getStatusCode() === 200) {
            $user->update(['onboarding_completed_at' => now()]);
            // Send welcome notification
            $user->notify(new WelcomePatient($user));
        }

        return $response;
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|string|in:male,female,other,prefer_not_to_say',
            'currency' => 'nullable|string|in:NGN,USD',
            // Patient specific fields
            'medical_history' => 'nullable|array',
            'emergency_contact' => 'nullable|array',
            'preferences' => 'nullable|array',
            'avatar' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $request->only(['first_name', 'last_name', 'phone', 'date_of_birth', 'gender', 'currency']);

        if ($request->hasFile('avatar')) {
            if ($user->profile_photo) {
                Storage::delete($user->profile_photo);
            }
            $path = $request->file('avatar')->store("profile-photos/{$user->id}", 'public');
            $data['profile_photo'] = $path;
        }

        $user->update($data);

        // Update or create patient details
        $patientData = $request->only(['medical_history', 'emergency_contact', 'preferences']);
        if (! empty($patientData)) {
            $user->patient()->updateOrCreate(
                ['user_id' => $user->id],
                $patientData
            );
        }

        return $this->sendResponse($user->load('patient'), 'Profile updated successfully.');
    }

    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|max:2048',
        ]);

        $user = $request->user();
        if ($user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
        }
        $path = $request->file('photo')->store("profile-photos/{$user->id}", 'public');
        $user->update(['profile_photo' => $path]);

        return $this->sendResponse(['photo_url' => Storage::url($path)], 'Profile photo uploaded successfully.');
    }

    public function getSettings(Request $request)
    {
        $user = $request->user();
        $preferences = $user->patient?->preferences ?? [];

        return $this->sendResponse($preferences, 'Settings retrieved successfully.');
    }

    public function updateSettings(Request $request)
    {
        $user = $request->user();
        // Assuming settings are stored in preferences json in patient model or user model
        // For now, let's assume patient preferences
        $settings = $request->all();

        $patient = $user->patient;
        if ($patient) {
            $currentPreferences = $patient->preferences ?? [];
            $patient->preferences = array_merge($currentPreferences, $settings);
            $patient->save();
        }

        return $this->sendResponse([], 'Settings updated successfully.');
    }

    public function updateNotifications(Request $request)
    {
        // Similar to settings
        return $this->updateSettings($request);
    }

    public function biometric(Request $request)
    {
        // Store biometric preference/key
        $request->validate([
            'biometric_enabled' => 'required|boolean',
            'device_id' => 'required|string',
        ]);

        // Logic to store this against user's device
        return $this->sendResponse([], 'Biometric settings updated.');
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return $this->sendError('Current password does not match.', [], 400);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        Mail::to($user->email)->send(new PasswordChanged(
            userName: $user->name,
            changedAt: now()->format('F j, Y \a\t g:i A T'),
        ));

        return $this->sendResponse([], 'Password changed successfully.');
    }

    public function devices(Request $request)
    {
        $currentTokenId = $request->user()->currentAccessToken()?->id;

        $devices = $request->user()->tokens->map(fn ($token) => [
            'id'          => $token->id,
            'device_name' => $token->name === 'auth_token' ? 'This Device' : $token->name,
            'last_active' => $token->last_used_at ?? $token->created_at,
            'is_current'  => $token->id === $currentTokenId,
        ]);

        return $this->sendResponse($devices, 'Active sessions retrieved successfully.');
    }

    public function unlinkDevice(Request $request, $deviceId)
    {
        $request->user()->tokens()->where('id', $deviceId)->delete();

        return $this->sendResponse([], 'Device unlinked successfully.');
    }

    public function deleteAccount(Request $request)
    {
        $request->validate([
            'password' => 'required',
            'confirmation' => 'required|in:DELETE MY ACCOUNT',
        ]);

        $user = $request->user();
        if (! Hash::check($request->password, $user->password)) {
            return $this->sendError('Incorrect password.');
        }

        $user->delete(); // Soft delete
        $user->tokens()->delete();

        return $this->sendResponse([], 'Account deleted successfully.');
    }

    public function getPreferences(Request $request)
    {
        $user = $request->user();

        return $this->sendResponse($user->preferences ?? [], 'Preferences retrieved.');
    }

    public function updatePreferences(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'preferences' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $existing = $user->preferences ?? [];
        $incoming = $request->input('preferences', []);
        $merged = array_merge($existing, $incoming);

        $user->update(['preferences' => $merged]);

        return $this->sendResponse($merged, 'Preferences updated.');
    }
}
