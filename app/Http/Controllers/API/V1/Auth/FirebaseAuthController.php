<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\API\BaseController;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\PlatformSettingsService;
use App\Services\UserReferralService;
use Illuminate\Http\Request;
use App\Helpers\TotpHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Kreait\Laravel\Firebase\Facades\Firebase;

class FirebaseAuthController extends BaseController
{
    protected $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Authenticate via Firebase ID Token
     */
    public function authenticate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_token'        => 'required|string',
            'role_slug'       => 'nullable|string|exists:roles,slug',
            'timezone'        => 'nullable|string',
            'signup_source'   => 'nullable|string|max:100',
            'utm_medium'      => 'nullable|string|max:100',
            'utm_campaign'    => 'nullable|string|max:100',
            'referral_code'   => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        try {
            $auth = Firebase::auth();
            $verifiedIdToken = $auth->verifyIdToken($request->id_token);
            $firebaseUid    = $verifiedIdToken->claims()->get('sub');
            $firebaseUser   = $auth->getUser($firebaseUid);
            $signInProvider = $verifiedIdToken->claims()->get('firebase')['sign_in_provider'] ?? null;
            $isPasswordAuth = $signInProvider === 'password';

            // Normalise provider to a readable value stored on the user
            $authProvider = match ($signInProvider) {
                'password'   => 'email',
                'google.com' => 'google',
                'phone'      => 'phone',
                'anonymous'  => 'anonymous',
                default      => $signInProvider ?? 'unknown',
            };

            // Find user by firebase_uid or email
            $user = User::where('firebase_uid', $firebaseUid)->first();

            if (! $user && $firebaseUser->email) {
                $user = User::where('email', $firebaseUser->email)->first();
                if ($user) {
                    // Link existing user to Firebase
                    $user->update(['firebase_uid' => $firebaseUid]);
                }
            }

            if ($user) {
                // Update timezone if provided
                if ($request->timezone) {
                    $user->update(['timezone' => $request->timezone]);
                }
            } else {
                // Create new user
                $roleSlug = $request->role_slug ?? 'patient';
                $role = Role::where('slug', $roleSlug)->first();

                // Extract names from display name if available
                $displayName = $firebaseUser->displayName ?? '';
                $nameParts = explode(' ', $displayName, 2);
                $firstName = $nameParts[0] ?? 'User';
                $lastName = $nameParts[1] ?? (isset($nameParts[0]) ? '' : 'Onwynd');

                // For anonymous Firebase users, prefer guest-supplied email/name over generated placeholders
                $isAnonymous = ! $firebaseUser->email;
                if ($isAnonymous && $request->filled('guest_email')) {
                    $firstName = $request->string('guest_first_name')->toString() ?: 'Guest';
                    $lastName = '';
                }

                // Resolve ambassador referral code if tracking is enabled
                $ambassadorReferralCode = null;
                $resolvedReferralCode   = null;
                $ambassadorTrackingEnabled = filter_var(
                    PlatformSettingsService::get('ambassador_referral_tracking_enabled', 'false'),
                    FILTER_VALIDATE_BOOLEAN
                );
                if ($ambassadorTrackingEnabled && $request->filled('referral_code')) {
                    $resolvedReferralCode = ReferralCode::where('code', strtoupper($request->input('referral_code')))->first();
                    if ($resolvedReferralCode && $resolvedReferralCode->isValid()) {
                        $ambassadorReferralCode = $resolvedReferralCode->code;
                    }
                }

                $user = User::create([
                    'firebase_uid'                => $firebaseUid,
                    'is_anonymous'                => $isAnonymous,
                    'first_name'                  => $firstName,
                    'last_name'                   => $lastName,
                    'email'                       => $firebaseUser->email ?? ($request->filled('guest_email') ? $request->string('guest_email')->toString() : $firebaseUid.'@anonymous.onwynd.com'),
                    'phone'                       => $firebaseUser->phoneNumber,
                    'password'                    => Hash::make(Str::random(32)),
                    'has_password'                => $isPasswordAuth,
                    'auth_provider'               => $authProvider,
                    'signup_source'               => $request->input('signup_source') ?: 'direct',
                    'signup_utm_medium'           => $request->input('utm_medium'),
                    'signup_utm_campaign'         => $request->input('utm_campaign'),
                    'referred_by_ambassador_code' => $ambassadorReferralCode,
                    'role_id'                     => $role ? $role->id : null,
                    'email_verified_at'           => $firebaseUser->emailVerified ? now() : null,
                    'is_active'                   => true,
                    'timezone'                    => $request->timezone,
                ]);

                // Create profiles
                if ($roleSlug === 'patient') {
                    UserProfile::firstOrCreate(['user_id' => $user->id]);
                }

                // Track ambassador referral
                if ($resolvedReferralCode) {
                    $resolvedReferralCode->increment('uses_count');
                    Referral::create([
                        'ambassador_id'    => $resolvedReferralCode->ambassador_id,
                        'referred_user_id' => $user->id,
                        'status'           => 'pending',
                    ]);
                } elseif ($request->filled('referral_code')) {
                    // Try user-to-user referral code (freemium / paid tier)
                    app(UserReferralService::class)->processSignupReferral($user, $request->input('referral_code'));
                }
            }

            $user->load('role');

            // 2FA challenge — only for email/password sign-ins, not Google/social OAuth
            if ($isPasswordAuth && $user->two_factor_enabled && $user->two_factor_secret) {
                $challengeToken = Str::uuid()->toString();
                Cache::put("2fa_challenge_{$challengeToken}", $user->id, now()->addMinutes(5));

                return response()->json([
                    'success'         => true,
                    'requires_2fa'    => true,
                    'challenge_token' => $challengeToken,
                    'message'         => 'Two-factor authentication required.',
                ]);
            }

            $token = $user->createToken($this->deviceTokenName($request->userAgent() ?? ''))->plainTextToken;

            return $this->sendResponse([
                'user' => $user,
                'token' => $token,
            ], 'Authenticated via Firebase successfully.')->cookie(
                $this->makeAuthCookie($request, $token),
            );

        } catch (\Exception $e) {
            Log::error('Firebase authentication failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('Authentication failed.', ['error' => $e->getMessage()], 401);
        }
    }

    /**
     * Complete a 2FA challenge after Firebase authentication.
     *
     * POST /api/v1/auth/firebase/two-factor/challenge
     * Body: { challenge_token: string, code: string }
     */
    public function twoFactorChallenge(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => 'required|string',
            'code'            => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $cacheKey = '2fa_challenge_'.$request->challenge_token;
        $userId   = Cache::get($cacheKey);

        if (! $userId) {
            return $this->sendError('Challenge expired or invalid. Please sign in again.', [], 401);
        }

        $user = User::find($userId);

        if (! $user || ! $user->two_factor_secret) {
            Cache::forget($cacheKey);
            return $this->sendError('Authentication failed.', [], 401);
        }

        $secret = decrypt($user->two_factor_secret);

        if (! TotpHelper::verify($secret, $request->code)) {
            return $this->sendError('Invalid code — please try again.', [], 422);
        }

        Cache::forget($cacheKey);

        $user->load('role');
        $token = $user->createToken($this->deviceTokenName($request->userAgent() ?? ''))->plainTextToken;

        Log::info('2FA challenge passed', ['user_id' => $user->id]);

        return $this->sendResponse([
            'user'  => $user,
            'token' => $token,
        ], 'Authenticated successfully.')->cookie(
            $this->makeAuthCookie($request, $token),
        );
    }
}
