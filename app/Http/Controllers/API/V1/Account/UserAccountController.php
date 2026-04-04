<?php

namespace App\Http\Controllers\API\V1\Account;

use App\Helpers\TotpHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangePasswordRequest;
use App\Http\Requests\Account\UpdateEmailRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Mail\PasswordChanged;
use App\Models\LoginHistory;
use App\Models\NotificationSetting;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class UserAccountController extends Controller
{
    public function __construct() {}

    /**
     * Get user profile
     *
     * GET /api/v1/account/profile
     */
    public function getProfile(): JsonResponse
    {
        try {
            /** @var User $user */
            /** @var User $user */
            $user = Auth::user();

            Log::info('Account: Get profile', ['user_id' => $user->id]);

            $user->loadMissing('profile', 'patient', 'notificationSetting');

            $patient = $user->patient()->first();
            $emergencyContact = $patient?->emergency_contact ?? [];

            $profileData = [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'bio' => $user->profile?->bio,
                'profile_photo' => $user->profile_photo_url,
                'date_of_birth' => $user->date_of_birth,
                'gender' => $user->gender,
                'country' => $user->country,
                'state' => $user->state,
                'city' => $user->city,
                'currency' => $user->currency,
                'is_verified' => $user->email_verified_at !== null,
                'email_verified_at' => $user->email_verified_at,
                'two_factor_enabled' => $user->two_factor_enabled,
                'last_login' => $user->last_login,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'emergency_contact_name'  => $emergencyContact['name']  ?? null,
                'emergency_contact_phone' => $emergencyContact['phone'] ?? null,
            ];

            // Add role-specific data
            if ($user->role === 'therapist' && $user->therapist) {
                $profileData['therapist'] = [
                    'specialization' => $user->therapist->specialization,
                    'years_of_experience' => $user->therapist->years_of_experience,
                    'is_verified' => $user->therapist->is_verified,
                    'average_rating' => round($user->therapist->ratings()->avg('rating') ?? 0, 2),
                    'total_reviews' => $user->therapist->ratings()->count(),
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile retrieved',
                'data' => $profileData,
            ]);

        } catch (Exception $e) {
            Log::error('Account: Get profile failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile',
            ], 400);
        }
    }

    /**
     * Update user profile
     *
     * PUT /api/v1/account/profile
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            try {
                /** @var User $user */
                $user = Auth::user();

                Log::info('Account: Updating profile', ['user_id' => $user->id]);

                $updateData = [];

                // Basic info â€” accept full_name (split into first/last) OR first_name + last_name directly.
                // The User model stores first_name and last_name as separate DB columns;
                // full_name is a computed accessor and is NOT fillable.
                if ($request->has('full_name')) {
                    $parts = explode(' ', trim($request->full_name), 2);
                    $updateData['first_name'] = $parts[0] ?? '';
                    $updateData['last_name'] = $parts[1] ?? '';
                } else {
                    if ($request->has('first_name')) {
                        $updateData['first_name'] = $request->first_name;
                    }
                    if ($request->has('last_name')) {
                        $updateData['last_name'] = $request->last_name;
                    }
                }

                if ($request->has('phone')) {
                    $updateData['phone'] = $request->phone;
                }


                if ($request->has('date_of_birth')) {
                    $updateData['date_of_birth'] = $request->date_of_birth;
                }

                if ($request->has('gender')) {
                    $updateData['gender'] = $request->gender;
                }

                if ($request->has('country')) {
                    $updateData['country'] = $request->country;
                }

                if ($request->has('state')) {
                    $updateData['state'] = $request->state;
                }

                if ($request->has('city')) {
                    $updateData['city'] = $request->city;
                }

                // Handle avatar: file upload takes priority, then Gravatar style selection.
                if ($request->hasFile('avatar')) {
                    if ($user->profile_photo && !filter_var($user->profile_photo, FILTER_VALIDATE_URL)) {
                        Storage::disk('public')->delete($user->profile_photo);
                    }
                    $path = $request->file('avatar')->store("profile-photos/{$user->id}", 'public');
                    $updateData['profile_photo'] = $path;
                } elseif ($request->filled('gravatar_style')) {
                    $hash = md5(strtolower(trim($user->email)));
                    $updateData['profile_photo'] = "https://www.gravatar.com/avatar/{$hash}?d={$request->gravatar_style}&s=400&r=pg";
                }

                if ($request->has('currency')) {
                    $updateData['currency'] = $request->currency;
                }

                $user->update($updateData);

                if ($request->has('bio')) {
                    $user->profile()->updateOrCreate(
                        ['user_id' => $user->id],
                        ['bio' => $request->bio]
                    );
                }

                // Save emergency contact to patient record
                if ($request->hasAny(['emergency_contact_name', 'emergency_contact_phone'])) {
                    $emergencyContact = [
                        'name'  => $request->emergency_contact_name ?? '',
                        'phone' => $request->emergency_contact_phone ?? '',
                    ];
                    $user->patient()->updateOrCreate(
                        ['user_id' => $user->id],
                        ['emergency_contact' => $emergencyContact]
                    );
                }

                Log::info('Account: Profile updated', ['user_id' => $user->id]);

                $patient = $user->patient()->first();
                $emergencyContact = $patient?->emergency_contact ?? [];

                return response()->json([
                    'success' => true,
                    'message' => 'Profile updated successfully',
                    'data' => [
                        'user_id'                 => $user->id,
                        'full_name'               => $user->full_name,
                        'updated_at'              => $user->updated_at,
                        'emergency_contact_name'  => $emergencyContact['name']  ?? null,
                        'emergency_contact_phone' => $emergencyContact['phone'] ?? null,
                    ],
                ]);

            } catch (Exception $e) {
                Log::error('Account: Update profile failed', [
                    'user_id' => Auth::id(),
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update profile: '.$e->getMessage(),
                ], 400);
            }
        });
    }

    /**
     * Change password
     *
     * POST /api/v1/account/change-password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            // Verify current password
            if (! Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                ], 401);
            }

            Log::info('Account: Changing password', ['user_id' => $user->id]);

            $user->update(['password' => Hash::make($request->new_password), 'has_password' => true]);

            // Revoke all other tokens
            $currentToken = $user->currentAccessToken();
            if ($currentToken) {
                $user->tokens()->where('id', '!=', $currentToken->id)->delete();
            }

            Log::info('Account: Password changed', ['user_id' => $user->id]);

            Mail::to($user->email)->send(new PasswordChanged(
                userName: $user->name,
                changedAt: now()->format('F j, Y \a\t g:i A T'),
            ));

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully',
                'data' => [
                    'user_id' => $user->id,
                    'message' => 'All other sessions have been logged out',
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Account: Change password failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
            ], 400);
        }
    }

    /**
     * Set a password for the first time (Google/social users who have no password).
     * No current password required â€” only allowed when has_password is false.
     *
     * POST /api/v1/account/password/set
     */
    public function setInitialPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        /** @var User $user */
        $user = Auth::user();

        if ($user->has_password) {
            return response()->json([
                'success' => false,
                'message' => 'Use the change-password endpoint to update an existing password.',
            ], 403);
        }

        $user->update([
            'password'     => Hash::make($request->password),
            'has_password' => true,
        ]);

        Log::info('Account: Initial password set', ['user_id' => $user->id]);

        return response()->json([
            'success' => true,
            'message' => 'Password set successfully. You can now sign in with email and password.',
        ]);
    }

    /**
     * Update email address
     *
     * POST /api/v1/account/change-email
     */
    public function updateEmail(UpdateEmailRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            Log::info('Account: Updating email', [
                'user_id' => $user->id,
                'new_email' => $request->new_email,
            ]);

            // Check if email already exists
            if (User::where('email', $request->new_email)->where('id', '!=', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already in use',
                ], 400);
            }

            $user->update([
                'email' => $request->new_email,
                'email_verified_at' => null, // Reset verification
            ]);

            Log::info('Account: Email updated', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Email updated successfully',
                'data' => [
                    'user_id' => $user->id,
                    'new_email' => $user->email,
                    'verification_pending' => true,
                    'message' => 'Please verify your new email address',
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Account: Update email failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update email',
            ], 400);
        }
    }

    /**
     * Step 1 of 2FA setup â€” generate a TOTP secret and return the provisioning URI.
     * The secret is stored (but two_factor_enabled remains false) until the user
     * verifies a live code in the enable endpoint.
     *
     * POST /api/v1/account/two-factor/setup
     */
    public function setupTwoFactor(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            $secret = TotpHelper::generateSecret();
            $uri    = TotpHelper::getUri($secret, $user->email, config('app.name'));

            // Persist the pending secret (not yet enabled)
            $user->update(['two_factor_secret' => encrypt($secret)]);

            Log::info('Account: 2FA setup initiated', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Scan the QR code or enter the setup key in your authenticator app, then confirm with a 6-digit code.',
                'data' => [
                    'secret' => $secret,          // Base32 setup key for manual entry
                    'uri'    => $uri,              // otpauth:// URI â€” use to generate QR
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Account: 2FA setup failed', ['user_id' => Auth::id(), 'message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to set up 2FA'], 400);
        }
    }

    /**
     * Step 2 â€” verify the first TOTP code to confirm the user scanned correctly,
     * then mark 2FA as enabled.
     *
     * POST /api/v1/account/two-factor/enable   { code: "123456" }
     */
    public function enableTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|digits:6']);

        try {
            /** @var User $user */
            $user = Auth::user();

            if (! $user->two_factor_secret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Start setup first via POST /account/two-factor/setup',
                ], 400);
            }

            $secret = decrypt($user->two_factor_secret);

            if (! TotpHelper::verify($secret, $request->code)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid code â€” please try again or check your device clock.',
                ], 422);
            }

            $user->update(['two_factor_enabled' => true]);

            Log::info('Account: 2FA enabled', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Two-factor authentication is now active.',
                'data'    => ['two_factor_enabled' => true],
            ]);

        } catch (Exception $e) {
            Log::error('Account: Enable 2FA failed', ['user_id' => Auth::id(), 'message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to enable 2FA'], 400);
        }
    }

    /**
     * Disable 2FA â€” requires a valid live TOTP code (not the account password).
     *
     * POST /api/v1/account/two-factor/disable   { code: "123456" }
     */
    public function disableTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|digits:6']);

        try {
            /** @var User $user */
            $user = Auth::user();

            if (! $user->two_factor_enabled || ! $user->two_factor_secret) {
                return response()->json(['success' => false, 'message' => '2FA is not enabled'], 400);
            }

            $secret = decrypt($user->two_factor_secret);

            if (! TotpHelper::verify($secret, $request->code)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid code â€” please check your authenticator app.',
                ], 422);
            }

            Log::info('Account: Disabling 2FA', ['user_id' => $user->id]);

            $user->update([
                'two_factor_enabled' => false,
                'two_factor_secret'  => null,
            ]);

            Log::info('Account: 2FA disabled', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Two-factor authentication has been disabled.',
                'data'    => ['two_factor_enabled' => false],
            ]);

        } catch (Exception $e) {
            Log::error('Account: Disable 2FA failed', ['user_id' => Auth::id(), 'message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to disable 2FA'], 400);
        }
    }

    /**
     * Get notification settings
     *
     * GET /api/v1/account/notification-settings
     */
    public function getNotificationSettings(): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            Log::info('Account: Get notification settings', ['user_id' => $user->id]);

            $notificationSetting = $user->notificationSetting()->firstOrCreate(
                ['user_id' => $user->id],
                array_merge(
                    NotificationSetting::getDefaults(),
                    ['sms_notifications' => $user->phone_verified_at !== null]
                )
            );

            $settings = $notificationSetting->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Notification settings retrieved',
                'data' => [
                    'user_id' => $user->id,
                    'settings' => $settings,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Account: Get notification settings failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification settings',
            ], 400);
        }
    }

    /**
     * Update notification settings
     *
     * PUT /api/v1/account/notification-settings
     */
    public function updateNotificationSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_notifications' => 'sometimes|boolean',
            'sms_notifications' => 'sometimes|boolean',
            'push_notifications' => 'sometimes|boolean',
            'whatsapp_notifications' => 'sometimes|boolean',
            'session_reminders' => 'sometimes|boolean',
            'message_notifications' => 'sometimes|boolean',
            'billing_notifications' => 'sometimes|boolean',
            'promotional_emails' => 'sometimes|boolean',
            'newsletter' => 'sometimes|boolean',
            'community_updates' => 'sometimes|boolean',
            'appointment_reminders' => 'sometimes|boolean',
            'wellbeing_checkins' => 'sometimes|boolean',
            'platform_updates' => 'sometimes|boolean',
            'channel_preferences' => 'sometimes|array',
            'email_frequency' => 'sometimes|in:never,daily,weekly,monthly',
        ]);

        try {
            /** @var User $user */
            $user = Auth::user();

            Log::info('Account: Updating notification settings', ['user_id' => $user->id]);

            $notificationSetting = $user->notificationSetting()->firstOrCreate(
                ['user_id' => $user->id],
                array_merge(
                    NotificationSetting::getDefaults(),
                    ['sms_notifications' => $user->phone_verified_at !== null]
                )
            );

            if (! empty($validated)) {
                $notificationSetting->update($validated);
                $notificationSetting->refresh();
            }

            Log::info('Account: Notification settings updated', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Notification settings updated',
                'data' => [
                    'user_id' => $user->id,
                    'settings' => $notificationSetting,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Account: Update notification settings failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification settings',
            ], 400);
        }
    }

    /**
     * Get account activity history
     *
     * GET /api/v1/account/history
     */
    public function getAccountHistory(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $perPage = $request->get('per_page', 20);

            Log::info('Account: Get account history', ['user_id' => $user->id]);

            // Get login history
            $history = LoginHistory::where('user_id', $user->id)
                ->orderByDesc('login_at')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Account history retrieved',
                'data' => [
                    'history' => $history->map(fn ($log) => [
                        'id' => $log->id,
                        'action' => 'Login',
                        'ip_address' => $log->ip_address,
                        'user_agent' => $log->user_agent,
                        'device_type' => $this->detectDeviceType($log->user_agent),
                        'browser' => $this->detectBrowser($log->user_agent),
                        'location' => $log->location ?? 'Unknown',
                        'timestamp' => $log->login_at,
                        'is_current' => false, // Implement logic to detect current session
                    ]),
                    'pagination' => [
                        'total' => $history->total(),
                        'count' => $history->count(),
                        'per_page' => $history->perPage(),
                        'current_page' => $history->currentPage(),
                        'last_page' => $history->lastPage(),
                    ],
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Account: Get history failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve account history',
            ], 400);
        }
    }

    /**
     * Request account deletion
     *
     * POST /api/v1/account/delete
     */
    public function requestAccountDeletion(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            if (! $request->has('password')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is required',
                ], 400);
            }

            // Verify password
            if (! Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is incorrect',
                ], 401);
            }

            Log::info('Account: Requesting deletion', ['user_id' => $user->id]);

            // Mark for deletion (with grace period)
            $user->update([
                'marked_for_deletion' => true,
                'deletion_requested_at' => now(),
                'deletion_scheduled_at' => now()->addDays(30), // 30-day grace period
            ]);

            Log::info('Account: Deletion requested', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Account deletion requested',
                'data' => [
                    'user_id' => $user->id,
                    'deletion_requested_at' => $user->deletion_requested_at,
                    'deletion_scheduled_at' => $user->deletion_scheduled_at,
                    'message' => 'Your account will be permanently deleted on '.$user->deletion_scheduled_at->format('Y-m-d').'. You can cancel this request within 30 days.',
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Account: Delete request failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to request account deletion',
            ], 400);
        }
    }

    /**
     * Cancel account deletion request
     *
     * POST /api/v1/account/cancel-deletion
     */
    public function cancelAccountDeletion(): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            if (! $user->marked_for_deletion) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pending deletion request',
                ], 400);
            }

            Log::info('Account: Cancelling deletion', ['user_id' => $user->id]);

            $user->update([
                'marked_for_deletion' => false,
                'deletion_requested_at' => null,
                'deletion_scheduled_at' => null,
            ]);

            Log::info('Account: Deletion cancelled', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Account deletion cancelled',
                'data' => [
                    'user_id' => $user->id,
                    'message' => 'Your account deletion has been cancelled',
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Account: Cancel deletion failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel account deletion',
            ], 400);
        }
    }

    /**
     * Detect device type from user agent
     *
     * @return string Device type
     */
    private function detectDeviceType(string $userAgent): string
    {
        if (preg_match('/mobile|android|iphone|ipod|blackberry|iemobile|opera mini/i', $userAgent)) {
            return 'Mobile';
        } elseif (preg_match('/tablet|ipad|playbook|silk/i', $userAgent)) {
            return 'Tablet';
        }

        return 'Desktop';
    }

    /**
     * Detect browser from user agent
     *
     * @return string Browser name
     */
    private function detectBrowser(string $userAgent): string
    {
        if (preg_match('/firefox/i', $userAgent)) {
            return 'Firefox';
        } elseif (preg_match('/chrome/i', $userAgent)) {
            return 'Chrome';
        } elseif (preg_match('/safari/i', $userAgent)) {
            return 'Safari';
        } elseif (preg_match('/opr\//i', $userAgent)) {
            return 'Opera';
        } elseif (preg_match('/edg/i', $userAgent)) {
            return 'Edge';
        }

        return 'Unknown';
    }
}


