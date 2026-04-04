<?php

namespace App\Http\Controllers\API\V1\Therapist;

use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\UpdateAvailabilityRequest;
use App\Http\Requests\Therapist\UpdateBankDetailsRequest;
use App\Http\Requests\Therapist\UpdateProfileRequest;
use App\Models\BankAccount;
use App\Models\Therapist;
use App\Models\TherapistSchedule;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TherapistProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get therapist profile
     *
     * GET /api/v1/therapist/profile
     */
    public function show(): JsonResponse
    {
        try {
            $user = Auth::user();
            $therapist = $user->therapist;

            if (! $therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a therapist',
                ], 404);
            }

            Log::info('Therapist: Get profile', ['therapist_id' => $therapist->id]);

            $stats = $this->stats($therapist);

            // Load TherapistProfile for admin verification data (rejection_reason, verified_at, etc.) and terms
            $verificationProfile = $user->therapistProfile;

            return response()->json([
                'success' => true,
                'message' => 'Profile retrieved',
                'data' => [
                    'id' => $therapist->id,
                    'full_name' => $therapist->full_name,
                    'email' => $therapist->user->email,
                    'phone' => $therapist->phone,
                    'bio' => $therapist->bio,
                    'specialization' => $therapist->specialization,
                    'qualification' => $therapist->qualification,
                    'years_of_experience' => $therapist->years_of_experience,
                    'license_number' => $therapist->license_number,
                    'is_verified' => $therapist->is_verified,
                    'verification_date' => $therapist->verification_date,
                    'hourly_rate' => $therapist->hourly_rate,
                    'currency' => $therapist->payout_currency ?? 'NGN',
                    'introductory_rate' => $therapist->introductory_rate,
                    'introductory_sessions_count' => $therapist->introductory_sessions_count,
                    'introductory_rate_active' => (bool) $therapist->introductory_rate_active,
                    'status' => $therapist->status,
                    'avatar_url' => $therapist->avatar_url,
                    'certificate_url' => $therapist->certificate_url,
                    'languages' => json_decode($therapist->languages) ?? [],
                    'areas_of_focus' => json_decode($therapist->areas_of_focus) ?? [],
                    'stats' => $stats,
                    'bank_details' => $therapist->account_number ? [
                        'account_name' => $therapist->account_name,
                        'account_number' => $therapist->account_number, // Return full number so they can see it (or maybe masked is better but for editing we need full usually? actually masked is fine for display but for form pre-fill it might be issue if they save it back)
                        'bank_code' => $therapist->bank_code,
                        'bank_name' => $therapist->bank_name,
                        'is_verified' => (bool) $therapist->recipient_code,
                    ] : null,
                    // Admin verification status (from TherapistProfile)
                    'verification' => $verificationProfile ? [
                        'status' => $verificationProfile->status,
                        'is_verified' => $verificationProfile->is_verified,
                        'rejection_reason' => $verificationProfile->rejection_reason,
                        'rejected_at' => $verificationProfile->rejected_at,
                        'verified_at' => $verificationProfile->verified_at,
                        'has_certificate' => (bool) $verificationProfile->certificate_url,
                    ] : null,
                    'terms_accepted_at' => $verificationProfile?->terms_accepted_at,
                    'created_at' => $therapist->created_at,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Therapist: Get profile failed', [
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
     * Update therapist profile
     *
     * PUT /api/v1/therapist/profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            try {
                $user = Auth::user();
                $therapist = $user->therapist;

                if (! $therapist) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User is not a therapist',
                    ], 404);
                }

                Log::info('Therapist: Updating profile', ['therapist_id' => $therapist->id]);

                $updateData = [];

                // Basic info
                if ($request->has('full_name')) {
                    $updateData['full_name'] = $request->full_name;
                }

                if ($request->has('phone')) {
                    $updateData['phone'] = $request->phone;
                }

                if ($request->has('bio')) {
                    $updateData['bio'] = $request->bio;
                }

                if ($request->has('specialization')) {
                    $updateData['specialization'] = $request->specialization;
                }

                if ($request->has('qualification')) {
                    $updateData['qualification'] = $request->qualification;
                }

                if ($request->has('years_of_experience')) {
                    $updateData['years_of_experience'] = $request->years_of_experience;
                }

                if ($request->has('hourly_rate')) {
                    $updateData['hourly_rate'] = $request->hourly_rate;
                }

                if ($request->has('languages')) {
                    $updateData['languages'] = json_encode($request->languages);
                }

                if ($request->has('areas_of_focus')) {
                    $updateData['areas_of_focus'] = json_encode($request->areas_of_focus);
                }

                // International / onboarding fields
                foreach (['country_of_operation', 'timezone', 'payout_currency', 'available_for_nigeria', 'available_for_international', 'cultural_competencies', 'licensing_country'] as $field) {
                    if ($request->has($field)) {
                        $updateData[$field] = $request->input($field);
                    }
                }

                // Handle avatar upload
                if ($request->hasFile('avatar')) {
                    if ($therapist->avatar_url) {
                        Storage::disk('public')->delete($therapist->avatar_url);
                    }
                    $path = $request->file('avatar')->store("avatars/{$therapist->user_id}", 'public');
                    $updateData['avatar_url'] = $path;
                }

                // Handle certificate upload
                if ($request->hasFile('certificate')) {
                    if ($therapist->certificate_url) {
                        Storage::disk(config('filesystems.default'))->delete($therapist->certificate_url);
                    }

                    $folderName = str_replace(' ', '_', $therapist->user->name).'_'.$therapist->created_at->format('Y-m-d');
                    $fileName = 'certificate_'.time().'.'.$request->file('certificate')->getClientOriginalExtension();

                    $path = $request->file('certificate')->storeAs("documents/therapists/{$folderName}", $fileName, config('filesystems.default'));

                    $updateData['certificate_url'] = $path;
                }

                $therapist->update($updateData);

                Log::info('Therapist: Profile updated', ['therapist_id' => $therapist->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Profile updated successfully',
                    'data' => [
                        'therapist_id' => $therapist->id,
                        'full_name' => $therapist->full_name,
                        'updated_at' => $therapist->updated_at,
                    ],
                ]);
            } catch (Exception $e) {
                Log::error('Therapist: Update profile failed', [
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
     * Accept Therapist Terms — sets terms_accepted_at if not already set
     *
     * PATCH /api/v1/therapist/terms/accept
     */
    public function acceptTerms(): JsonResponse
    {
        try {
            $user = Auth::user();
            $profile = $user->therapistProfile;
            if (! $profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Therapist profile not found.',
                ], 404);
            }
            if (! $profile->terms_accepted_at) {
                $profile->terms_accepted_at = now();
                $profile->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Terms accepted.',
                'data' => ['terms_accepted_at' => $profile->terms_accepted_at],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept terms',
            ], 400);
        }
    }

    /**
     * Upload therapist certificate
     *
     * POST /api/v1/therapist/certificate
     */
    public function uploadCertificate(Request $request): JsonResponse
    {
        $request->validate([
            'certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        try {
            $user = Auth::user();
            $therapist = $user->therapist;

            if (! $therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a therapist',
                ], 404);
            }

            Log::info('Therapist: Uploading certificate', ['therapist_id' => $therapist->id]);

            // Delete old certificate if exists
            if ($therapist->certificate_url) {
                Storage::delete($therapist->certificate_url);
            }

            // Upload new certificate
            $file = $request->file('certificate');
            $path = $file->store('therapist/certificates', 'public');

            $therapist->update(['certificate_url' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Certificate uploaded successfully',
                'data' => [
                    'certificate_url' => Storage::url($path),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Therapist: Upload certificate failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload certificate',
            ], 400);
        }
    }

    /**
     * Get therapist schedule/availability
     *
     * GET /api/v1/therapist/availability
     */
    public function getAvailability(): JsonResponse
    {
        try {
            $user = Auth::user();
            $therapist = $user->therapist;

            if (! $therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a therapist',
                ], 404);
            }

            Log::info('Therapist: Get availability', ['therapist_id' => $therapist->id]);

            $schedule = $therapist->schedule()
                ->orderBy('day_of_week')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'day_of_week' => $s->day_of_week,
                    'day_name' => $this->getDayName($s->day_of_week),
                    'start_time' => $s->start_time,
                    'end_time' => $s->end_time,
                    'is_available' => $s->is_available,
                    'break_start' => $s->break_start,
                    'break_end' => $s->break_end,
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Availability retrieved',
                'data' => [
                    'therapist_id' => $therapist->id,
                    'schedule' => $schedule,
                    'timezone' => 'Africa/Lagos',
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Therapist: Get availability failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve availability',
            ], 400);
        }
    }

    /**
     * Update therapist availability
     *
     * PUT /api/v1/therapist/availability
     */
    public function updateAvailability(UpdateAvailabilityRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            try {
                $user = Auth::user();
                $therapist = $user->therapist;

                if (! $therapist) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User is not a therapist',
                    ], 404);
                }

                Log::info('Therapist: Updating availability', [
                    'therapist_id' => $therapist->id,
                    'day' => $request->day_of_week,
                ]);

                // Update or create schedule
                $schedule = $therapist->schedule()
                    ->where('day_of_week', $request->day_of_week)
                    ->first();

                if ($schedule) {
                    $schedule->update([
                        'start_time' => $request->start_time,
                        'end_time' => $request->end_time,
                        'is_available' => $request->is_available ?? true,
                        'break_start' => $request->break_start ?? null,
                        'break_end' => $request->break_end ?? null,
                    ]);
                } else {
                    TherapistSchedule::create([
                        'therapist_id' => $therapist->id,
                        'day_of_week' => $request->day_of_week,
                        'start_time' => $request->start_time,
                        'end_time' => $request->end_time,
                        'is_available' => $request->is_available ?? true,
                        'break_start' => $request->break_start ?? null,
                        'break_end' => $request->break_end ?? null,
                    ]);
                }

                Log::info('Therapist: Availability updated', ['therapist_id' => $therapist->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Availability updated',
                ]);
            } catch (Exception $e) {
                Log::error('Therapist: Update availability failed', [
                    'user_id' => Auth::id(),
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update availability: '.$e->getMessage(),
                ], 400);
            }
        });
    }

    /**
     * Get therapist reviews and ratings
     *
     * GET /api/v1/therapist/reviews
     */
    public function getReviews(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $therapist = $user->therapist;

            if (! $therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a therapist',
                ], 404);
            }

            $perPage = $request->get('per_page', 10);

            Log::info('Therapist: Get reviews', ['therapist_id' => $therapist->id]);

            $ratings = $therapist->ratings()
                ->with('user')
                ->orderByDesc('created_at')
                ->paginate($perPage);

            $avgRating = $therapist->ratings()->avg('rating') ?? 0;
            $totalReviews = $therapist->ratings()->count();

            return response()->json([
                'success' => true,
                'message' => 'Reviews retrieved',
                'data' => [
                    'average_rating' => round($avgRating, 2),
                    'total_reviews' => $totalReviews,
                    'rating_distribution' => [
                        '5_stars' => $therapist->ratings()->where('rating', 5)->count(),
                        '4_stars' => $therapist->ratings()->where('rating', 4)->count(),
                        '3_stars' => $therapist->ratings()->where('rating', 3)->count(),
                        '2_stars' => $therapist->ratings()->where('rating', 2)->count(),
                        '1_star' => $therapist->ratings()->where('rating', 1)->count(),
                    ],
                    'reviews' => $ratings->map(fn ($rating) => [
                        'id' => $rating->id,
                        'rating' => $rating->rating,
                        'feedback' => $rating->feedback,
                        'reviewer_name' => $rating->user->full_name,
                        'created_at' => $rating->created_at,
                    ]),
                    'pagination' => [
                        'total' => $ratings->total(),
                        'count' => $ratings->count(),
                        'per_page' => $ratings->perPage(),
                        'current_page' => $ratings->currentPage(),
                        'last_page' => $ratings->lastPage(),
                    ],
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Therapist: Get reviews failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reviews',
            ], 400);
        }
    }

    /**
     * Update bank details for payout
     *
     * PUT /api/v1/therapist/bank-details
     */
    public function updateBankDetails(UpdateBankDetailsRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            try {
                $user = Auth::user();
                $therapist = $user->therapist;

                if (! $therapist) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User is not a therapist',
                    ], 404);
                }

                Log::info('Therapist: Updating bank details', ['therapist_id' => $therapist->id]);

                // Use Paystack to verify account
                $bankAccount = $therapist->bankAccount;

                if ($bankAccount) {
                    $bankAccount->update([
                        'account_holder' => $request->account_holder,
                        'account_number' => $request->account_number,
                        'bank_code' => $request->bank_code,
                        'bank_name' => $request->bank_name,
                        'is_verified' => false, // Reset verification
                    ]);
                } else {
                    $bankAccount = BankAccount::create([
                        'therapist_id' => $therapist->id,
                        'account_holder' => $request->account_holder,
                        'account_number' => $request->account_number,
                        'bank_code' => $request->bank_code,
                        'bank_name' => $request->bank_name,
                        'is_verified' => false,
                    ]);
                }

                Log::info('Therapist: Bank details updated', ['therapist_id' => $therapist->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Bank details updated',
                    'data' => [
                        'account_holder' => $bankAccount->account_holder,
                        'account_number' => substr($bankAccount->account_number, -4),
                        'bank_code' => $bankAccount->bank_code,
                        'bank_name' => $bankAccount->bank_name,
                        'is_verified' => $bankAccount->is_verified,
                    ],
                ]);
            } catch (Exception $e) {
                Log::error('Therapist: Update bank details failed', [
                    'user_id' => Auth::id(),
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update bank details: '.$e->getMessage(),
                ], 400);
            }
        });
    }

    /**
     * Get therapist earnings summary
     *
     * GET /api/v1/therapist/earnings
     */
    public function getEarnings(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $therapist = $user->therapist;

            if (! $therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a therapist',
                ], 404);
            }

            $period = $request->get('period', 'month'); // month, year, all
            $startDate = $this->getStartDate($period);

            Log::info('Therapist: Get earnings', [
                'therapist_id' => $therapist->id,
                'period' => $period,
            ]);

            $sessions = $therapist->sessions()
                ->where('status', 'completed')
                ->where('created_at', '>=', $startDate)
                ->get();

            $totalEarnings = $sessions->sum('session_fee');
            $completedSessions = $sessions->count();

            // Get payouts in period
            $payouts = $therapist->payouts()
                ->where('created_at', '>=', $startDate)
                ->get();

            $totalPayouts = $payouts->sum('amount');

            return response()->json([
                'success' => true,
                'message' => 'Earnings retrieved',
                'data' => [
                    'period' => $period,
                    'total_earnings' => round($totalEarnings, 2),
                    'formatted_earnings' => '₦'.number_format($totalEarnings, 2),
                    'total_payouts' => round($totalPayouts, 2),
                    'formatted_payouts' => '₦'.number_format($totalPayouts, 2),
                    'pending_balance' => round($totalEarnings - $totalPayouts, 2),
                    'formatted_pending' => '₦'.number_format($totalEarnings - $totalPayouts, 2),
                    'completed_sessions' => $completedSessions,
                    'average_earning_per_session' => $completedSessions > 0
                        ? round($totalEarnings / $completedSessions, 2)
                        : 0,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Therapist: Get earnings failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve earnings',
            ], 400);
            }
        }

    /**
     * Get therapist financial flow/earnings summary
     *
     * GET /api/v1/therapist/financial-flow
     */
    public function financialFlow(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $therapist = $user->therapist;

            if (! $therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a therapist',
                ], 404);
            }

            $period = $request->get('period', 'month'); // month, year, all
            $startDate = $this->getStartDate($period);

            Log::info('Therapist: Get financial flow', [
                'therapist_id' => $therapist->id,
                'period' => $period,
            ]);

            $sessions = $therapist->sessions()
                ->where('status', 'completed')
                ->where('created_at', '>=', $startDate)
                ->get();

            $totalEarnings = $sessions->sum('session_fee');
            $completedSessions = $sessions->count();

            // Get payouts in period
            $payouts = $therapist->payouts()
                ->where('created_at', '>=', $startDate)
                ->get();

            $totalPayouts = $payouts->sum('amount');

            return response()->json([
                'success' => true,
                'message' => 'Financial flow retrieved',
                'data' => [
                    'period' => $period,
                    'total_earnings' => round($totalEarnings, 2),
                    'formatted_earnings' => '₦'.number_format($totalEarnings, 2),
                    'total_payouts' => round($totalPayouts, 2),
                    'formatted_payouts' => '₦'.number_format($totalPayouts, 2),
                    'pending_balance' => round($totalEarnings - $totalPayouts, 2),
                    'formatted_pending' => '₦'.number_format($totalEarnings - $totalPayouts, 2),
                    'completed_sessions' => $completedSessions,
                    'average_earning_per_session' => $completedSessions > 0
                        ? round($totalEarnings / $completedSessions, 2)
                        : 0,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Therapist: Get financial flow failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve financial flow',
            ], 400);
        }
    }

    /**
     * Get therapist schedule
     *
     * GET /api/v1/therapist/schedule
     */
    public function getSchedule(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $therapist = $user->therapist;

            if (! $therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a therapist',
                ], 404);
            }

            $view = $request->get('view', 'week'); // week, month
            $startDate = $request->get('start_date', now()->format('Y-m-d'));

            Log::info('Therapist: Get schedule', [
                'therapist_id' => $therapist->id,
                'view' => $view,
                'start_date' => $startDate,
            ]);

            $endDate = $view === 'week'
                ? Carbon::parse($startDate)->addWeek()
                : Carbon::parse($startDate)->endOfMonth();

            $sessions = $therapist->sessions()
                ->where('scheduled_date', '>=', $startDate)
                ->where('scheduled_date', '<=', $endDate)
                ->where('status', '!=', 'cancelled')
                ->orderBy('scheduled_date')
                ->orderBy('scheduled_time')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Schedule retrieved',
                'data' => [
                    'view' => $view,
                    'start_date' => $startDate,
                    'end_date' => $endDate->format('Y-m-d'),
                    'sessions' => $sessions->map(fn ($session) => [
                        'id' => $session->id,
                        'patient_name' => $session->user->full_name,
                        'patient_email' => $session->user->email,
                        'date' => $session->scheduled_date,
                        'time' => $session->scheduled_time,
                        'duration' => $session->duration_minutes,
                        'status' => $session->status,
                        'session_fee' => $session->session_fee,
                        'formatted_fee' => '₦'.number_format($session->session_fee, 2),
                        'notes' => $session->notes,
                    ]),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Therapist: Get schedule failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve schedule',
            ], 400);
        }
    }

    /**
     * Get therapist statistics
     *
     * @return array Statistics
     */
    public function stats(Therapist $therapist): array
    {
        return [
            'total_sessions' => $therapist->sessions()->count(),
            'completed_sessions' => $therapist->sessions()->where('status', 'completed')->count(),
            'cancelled_sessions' => $therapist->sessions()->where('status', 'cancelled')->count(),
            'average_rating' => round($therapist->ratings()->avg('rating') ?? 0, 2),
            'total_reviews' => $therapist->ratings()->count(),
            'total_earnings' => round($therapist->sessions()
                ->where('status', 'completed')
                ->sum('session_fee'), 2),
            'total_payouts' => round($therapist->payouts()->sum('amount'), 2),
        ];
    }

    /**
     * Get day name from day of week number
     *
     * @param  int  $dayOfWeek  0-6 (Sunday-Saturday)
     * @return string Day name
     */
    private function getDayName(int $dayOfWeek): string
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return $days[$dayOfWeek] ?? 'Unknown';
    }

    /**
     * Update therapist session rate and introductory pricing
     *
     * PUT /api/v1/therapist/profile/rate
     */
    public function updateRate(Request $request): JsonResponse
    {
        $request->validate([
            'hourly_rate'                => 'required|numeric|min:0',
            'has_35min_slot'             => 'boolean',
            'rate_35min'                 => 'nullable|integer|min:0',
            'introductory_rate'          => 'nullable|numeric|min:0',
            'introductory_sessions_count' => 'nullable|integer|min:1|max:10',
            'introductory_rate_active'   => 'boolean',
        ]);

        $user = Auth::user();
        $therapist = $user->therapist;

        if (! $therapist) {
            return response()->json(['success' => false, 'message' => 'Therapist profile not found'], 404);
        }

        $therapist->update($request->only([
            'hourly_rate',
            'has_35min_slot',
            'rate_35min',
            'introductory_rate',
            'introductory_sessions_count',
            'introductory_rate_active',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Rate updated successfully.',
            'data' => [
                'hourly_rate'                 => $therapist->hourly_rate,
                'currency'                    => $therapist->payout_currency ?? 'NGN',
                'introductory_rate'           => $therapist->introductory_rate,
                'introductory_sessions_count' => $therapist->introductory_sessions_count,
                'introductory_rate_active'    => (bool) $therapist->introductory_rate_active,
            ],
        ]);
    }

    /**
     * Get start date based on period
     *
     * @return Carbon Start date
     */
    private function getStartDate(string $period): Carbon
    {
        return match ($period) {
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            'all' => Carbon::minValue(),
            default => now()->startOfMonth()
        };
    }
}
