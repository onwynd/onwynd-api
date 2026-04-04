<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\API\BaseController;
use App\Models\Role;
use App\Models\TherapistInvite;
use App\Models\TherapistPatientInvite;
use App\Models\TherapySession;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseController
{
    protected $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @OA\PathItem(
     *      path="/api/v1/auth/register",
     *
     *      @OA\Post(
     *           operationId="register",
     *           tags={"Auth"},
     *           summary="Register a new user",
     *           description="Registers a new user and returns an access token",
     *
     *           @OA\RequestBody(
     *               required=true,
     *
     *               @OA\JsonContent(
     *                   required={"first_name","last_name","email","password","password_confirmation"},
     *
     *                   @OA\Property(property="first_name", type="string", example="John"),
     *                   @OA\Property(property="last_name", type="string", example="Doe"),
     *                   @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *                   @OA\Property(property="password", type="string", format="password", example="Password123!"),
     *                   @OA\Property(property="password_confirmation", type="string", format="password", example="Password123!"),
     *                   @OA\Property(property="phone", type="string", example="+1234567890"),
     *               ),
     *           ),
     *
     *           @OA\Response(
     *               response=201,
     *               description="Successful operation",
     *
     *               @OA\JsonContent(
     *
     *                   @OA\Property(property="success", type="boolean", example=true),
     *                   @OA\Property(property="message", type="string", example="User registered successfully."),
     *                   @OA\Property(property="data", type="object",
     *                       @OA\Property(property="user", type="object"),
     *                       @OA\Property(property="token", type="string")
     *                   )
     *               )
     *           ),
     *
     *           @OA\Response(
     *               response=422,
     *               description="Validation Error"
     *           )
     *      )
     * )
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'role_id' => 'nullable|integer|exists:roles,id',
            'role_slug' => 'nullable|string|exists:roles,slug',
            'guest_token'   => 'nullable|string|uuid',
            'display_name'  => 'nullable|string|max:50',
            'invite_token'         => 'nullable|string|max:64',
            'patient_invite_token' => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // Resolve role: prefer role_slug (slug-based, ID-independent), then role_id, then default patient
        if ($request->filled('role_slug')) {
            $role = Role::where('slug', $request->role_slug)->first();
            $roleId = $role ? $role->id : null;
        } elseif ($request->filled('role_id')) {
            $roleId = $request->role_id;
            $role = Role::find($roleId);
        } else {
            // Default to 'patient' role for public registration
            $role = Role::where('slug', 'patient')->first();
            $roleId = $role ? $role->id : null;
        }

        $user = $this->userRepository->create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'display_name' => $request->filled('display_name') ? $request->display_name : $request->first_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role_id' => $roleId,
            'gender' => 'prefer_not_to_say',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Auto-create profile based on role
        if ($role && $role->slug === 'patient') {
            \App\Models\Patient::firstOrCreate(['user_id' => $user->id]);
            \App\Models\UserProfile::firstOrCreate(['user_id' => $user->id]);
        } elseif ($role && $role->slug === 'therapist') {
            // Create both for now as therapists might have patient features too, or just specific therapist profile
            \App\Models\TherapistProfile::firstOrCreate([
                'user_id' => $user->id,
                'license_number' => 'PENDING-'.$user->id, // Placeholder
                'license_state' => 'Pending',
                'license_expiry' => now()->addYear(),
                'specializations' => [],
                'qualifications' => [],
                'languages' => [],
                'experience_years' => 0,
                'hourly_rate' => 0,
                'bio' => '',
            ]);
            // Also create UserProfile for generic settings
            \App\Models\UserProfile::firstOrCreate(['user_id' => $user->id]);
        }

        $user->load('role');

        $token = $user->createToken($this->deviceTokenName($request->userAgent() ?? ''))->plainTextToken;

        // Link guest assessment if guest_token is provided
        if ($request->filled('guest_token')) {
            try {
                $guestResult = \App\Models\GuestAssessmentResult::where('guest_token', $request->guest_token)
                    ->whereNull('linked_user_id')
                    ->first();

                if ($guestResult) {
                    // Link the guest assessment to the new user
                    $userResult = $guestResult->linkToUser($user);

                    Log::info('Guest assessment linked to user', [
                        'user_id' => $user->id,
                        'guest_token' => $request->guest_token,
                        'assessment_id' => $guestResult->assessment_id,
                    ]);
                }
            } catch (\Exception $e) {
                // Log the error but don't fail registration
                Log::error('Failed to link guest assessment during registration', [
                    'user_id' => $user->id,
                    'guest_token' => $request->guest_token,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Link patient to therapist when registering via a therapist patient-invite
        if ($request->filled('patient_invite_token') && $role && $role->slug === 'patient') {
            try {
                $patientInvite = TherapistPatientInvite::where('token', $request->patient_invite_token)
                    ->where('email', $user->email)
                    ->whereNull('accepted_at')
                    ->where('expires_at', '>', now())
                    ->with('therapist')
                    ->first();

                if ($patientInvite) {
                    // Mark invite accepted
                    $patientInvite->update(['accepted_at' => now()]);

                    // Create a pending-confirmation therapy session to link them
                    TherapySession::create([
                        'uuid'         => \Illuminate\Support\Str::uuid(),
                        'patient_id'   => $user->id,
                        'therapist_id' => $patientInvite->therapist_id,
                        'status'       => 'pending_confirmation',
                        'session_type' => 'individual',
                        'scheduled_at' => null,
                        'booking_notes' => 'Invited by therapist — awaiting scheduling.',
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to link patient invite during registration', [
                    'user_id' => $user->id,
                    'token'   => $request->patient_invite_token,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // Mark therapist invite as accepted if a valid token was supplied
        if ($request->filled('invite_token') && $role && $role->slug === 'therapist') {
            TherapistInvite::where('token', $request->invite_token)
                ->where('email', $user->email)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->update(['accepted_at' => now()]);
        }

        $result = [
            'user' => $user,
            'token' => $token,
        ];

        return $this->sendResponse($result, 'User registered successfully.', 201)->cookie(
            $this->makeAuthCookie($request, $token),
        );
    }

    /**
     * @OA\PathItem(
     *      path="/api/v1/auth/login",
     *
     *      @OA\Post(
     *           operationId="login",
     *           tags={"Auth"},
     *           summary="Login user",
     *           description="Login user and return access token",
     *
     *           @OA\RequestBody(
     *               required=true,
     *
     *               @OA\JsonContent(
     *                   required={"email","password"},
     *
     *                   @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *                   @OA\Property(property="password", type="string", format="password", example="Password123!"),
     *               ),
     *           ),
     *
     *           @OA\Response(
     *               response=200,
     *               description="Successful operation",
     *
     *               @OA\JsonContent(
     *
     *                   @OA\Property(property="success", type="boolean", example=true),
     *                   @OA\Property(property="message", type="string", example="User logged in successfully."),
     *                   @OA\Property(property="data", type="object",
     *                       @OA\Property(property="user", type="object"),
     *                       @OA\Property(property="token", type="string")
     *                   )
     *               )
     *           ),
     *
     *           @OA\Response(
     *               response=401,
     *               description="Unauthorized"
     *           )
     *      )
     * )
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $user = $this->userRepository->findByEmail($request->email);

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->sendError('Invalid credentials.', [], 401);
        }

        $user->load('role');

        $token = $user->createToken($this->deviceTokenName($request->userAgent() ?? ''))->plainTextToken;

        // Check if user is a therapist and if profile is complete
        $redirect = null;
        $locationAlert = null;
        if ($user->hasRole('therapist')) {
            // Check verification status
            $therapist = $user->therapistProfile; // Relation in User model
            if ($therapist && ! $therapist->is_verified) {
                // If not verified, they might need to go to onboarding/verification page
                // But for now, we just log them in. Frontend handles redirection based on role.
            }

            // IP-based location verification — records mismatches and flags account after threshold
            if ($therapist) {
                $locationService = new \App\Services\Therapist\LocationVerificationService();
                $locationAlert = $locationService->checkOnLogin($therapist, $request);
            }
        }

        $responseData = [
            'user'  => $user,
            'token' => $token,
        ];

        if ($locationAlert !== null) {
            $responseData['location_alert'] = $locationAlert;
        }

        return $this->sendResponse($responseData, 'User logged in successfully.')->cookie(
            $this->makeAuthCookie($request, $token),
        );
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->sendResponse([], 'User logged out successfully.')->cookie(
            $this->makeExpiredAuthCookie($request),
        );
    }

    public function user(Request $request)
    {
        $user = $request->user()->load(['role', 'profile', 'patient', 'therapistProfile']);

        return $this->sendResponse($user, 'User details retrieved successfully.');
    }

    /** Alias for user() — keeps compatibility with clients calling GET /auth/me */
    public function me(Request $request)
    {
        return $this->user($request);
    }

    public function refresh(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return $this->sendError('Unauthenticated.', [], 401);
        }

        $request->user()->currentAccessToken()?->delete();

        $token = $user->createToken($this->deviceTokenName($request->userAgent() ?? ''))->plainTextToken;

        return $this->sendResponse([
            'user' => $user->load('role'),
            'token' => $token,
        ], 'Session refreshed successfully.')->cookie(
            $this->makeAuthCookie($request, $token),
        );
    }

    public function exchangeToken(Request $request)
    {
        $user = $request->user();
        $token = $request->bearerToken() ?: $request->cookie('auth_token');

        if (! $user || ! is_string($token) || $token === '') {
            return $this->sendError('Unauthenticated.', [], 401);
        }

        $code = bin2hex(random_bytes(32));

        Cache::put("auth_exchange_code:{$code}", [
            'token' => $token,
            'used' => false,
            'expires_at' => now()->addSeconds(60)->timestamp,
        ], now()->addSeconds(60));

        return $this->sendResponse([
            'code' => $code,
        ], 'Exchange code generated successfully.');
    }

    public function exchange(Request $request)
    {
        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $this->sendError('Exchange code is required.', [], 422);
        }

        $cacheKey = "auth_exchange_code:{$code}";
        $payload = Cache::get($cacheKey);

        if (! is_array($payload)) {
            return $this->sendError('Exchange code expired.', [], 410);
        }

        $expired = ($payload['expires_at'] ?? 0) < now()->timestamp;
        $used = (bool) ($payload['used'] ?? false);

        if ($expired || $used) {
            Cache::forget($cacheKey);

            return $this->sendError('Exchange code is no longer valid.', [], 410);
        }

        $payload['used'] = true;
        Cache::put($cacheKey, $payload, now()->addSeconds(60));

        return $this->sendResponse([
            'token' => $payload['token'],
        ], 'Exchange completed successfully.');
    }

    public function social(Request $request)
    {
        // Placeholder for social login
        return $this->sendError('Social login not implemented yet.', [], 501);
    }

    public function verifyOtp(Request $request)
    {
        // Placeholder for OTP verification
        return $this->sendError('OTP verification not implemented yet.', [], 501);
    }
}
