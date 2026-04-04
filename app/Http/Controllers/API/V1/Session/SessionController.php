<?php

namespace App\Http\Controllers\API\V1\Session;

use App\Http\Controllers\Controller;
use App\Http\Requests\Session\BookSessionRequest;
use App\Http\Requests\Session\CancelSessionRequest;
use App\Http\Requests\Session\CompleteSessionRequest;
use App\Http\Requests\Session\FeedbackRequest;
use App\Http\Requests\Session\RescheduleSessionRequest;
use App\Jobs\SendSessionReminder;
use App\Mail\AppointmentBookingConfirmation;
use App\Mail\AppointmentPendingConfirmation;
use App\Mail\TherapistSessionRequestNotification;
use App\Models\Institutional\OrganizationMember;
use App\Models\OrganizationSessionLog;
use App\Models\Therapist;
use App\Models\User;
use App\Repositories\Contracts\TherapistRepositoryInterface;
use App\Repositories\Contracts\TherapyRepositoryInterface;
use App\Services\Currency\CurrencyService;
use App\Services\PaymentService\PaymentProcessor;
use App\Services\Quota\QuotaService;
use App\Services\Session\BookingValidationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SessionController extends Controller
{
    private $paymentProcessor;

    private $currencyService;

    private $therapyRepository;

    private $therapistRepository;

    private $quotaService;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        try {
            if (env('PAYMENTS_ENABLED', false)) {
                $this->paymentProcessor = app()->make(PaymentProcessor::class);
            }
            $this->currencyService = app()->bound(CurrencyService::class)
                ? app()->make(CurrencyService::class)
                : null;
            $this->quotaService = app()->bound(QuotaService::class)
                ? app()->make(QuotaService::class)
                : null;
            if (app()->bound(TherapyRepositoryInterface::class)) {
                $this->therapyRepository = app()->make(TherapyRepositoryInterface::class);
            }
            if (app()->bound(TherapistRepositoryInterface::class)) {
                $this->therapistRepository = app()->make(TherapistRepositoryInterface::class);
            }
        } catch (\Throwable $e) {
            Log::warning('SessionController: optional services not bound', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get available therapists with available slots
     *
     * GET /api/v1/sessions/therapists/available
     */
    public function getAvailableTherapists(Request $request): JsonResponse
    {
        try {
            $specialization = $request->get('specialization');
            $date = $request->get('date');

            Log::info('Session: Get available therapists', [
                'user_id' => Auth::id(),
                'specialization' => $specialization,
                'date' => $date,
            ]);

            $therapists = $this->therapyRepository->getAvailableTherapists($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Available therapists retrieved',
                'data' => [
                    'therapists' => $therapists,
                    'total' => $therapists->count(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Session: Get available therapists failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available therapists',
            ], 400);
        }
    }

    /**
     * Get active session for the current user
     *
     * GET /api/v1/sessions/active
     */
    public function getActiveSession(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // A session is active if it's currently in progress or starting now
            $session = \App\Models\TherapySession::where(function ($q) use ($user) {
                $q->where('patient_id', $user->id)
                    ->orWhere('therapist_id', $user->id);
            })
                ->whereIn('status', ['in_progress', 'ongoing', 'scheduled', 'confirmed', 'pending_confirmation'])
                ->where('scheduled_at', '<=', now()->addMinutes(5))
                ->where('scheduled_at', '>=', now()->subMinutes(90))
                ->with(['patient', 'therapist.user'])
                ->first();

            return response()->json([
                'success' => true,
                'data' => $session,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active session',
            ], 400);
        }
    }

    /**
     * Book a therapy session
     *
     * POST /api/v1/sessions/book
     */
    public function bookSession(BookSessionRequest $request): JsonResponse
    {
        // Declare $isAnonymous outside the transaction so the catch blocks can reference it.
        $isAnonymous = $request->boolean('is_anonymous');

        try {
            return DB::transaction(function () use ($request, $isAnonymous) {
                // Handle anonymous bookings
                // $isAnonymous already resolved above
                $user = $isAnonymous ? null : Auth::user();
                $anonymousSessionData = null; // Initialize anonymous session tracking data
                $anonymousFingerprint = null;

                if ($isAnonymous) {
                    $bookingValidationService = new BookingValidationService();
                    $bookingValidationService->validateAnonymousBooking($request);
                    $anonymousFingerprint = $bookingValidationService->generateAnonymousFingerprint($request);
                }

                // Support frontend param therapist_uuid as well as legacy therapist_id
                if ($request->filled('therapist_uuid')) {
                    $therapistUser = User::where('uuid', $request->therapist_uuid)->first();
                    if (! $therapistUser) {
                        throw new Exception('Therapist not found');
                    }
                    $therapist = \App\Models\Therapist::where('user_id', $therapistUser->id)->first();
                } else {
                    $therapist = $this->therapyRepository->findTherapist($request->therapist_id);
                }

                if (! $therapist) {
                    throw new Exception('Therapist not found');
                }

                // Corporate credit check - must be done before payment logic
                $organizationMembership = null;
                $isCorporateCovered = false;
                $amountCovered = 0;
                $amountChargedToUser = 0;

                if (! $isAnonymous && $user) {
                    $organizationMembership = OrganizationMember::with('organization')
                        ->where('user_id', $user->id)
                        ->where('role', 'member')
                        ->first();

                    if ($organizationMembership && $organizationMembership->organization) {
                        $org = $organizationMembership->organization;

                        // Check if organization is active and user has credits
                        if ($org->status === 'active' &&
                            $organizationMembership->sessions_used_this_month < $organizationMembership->sessions_limit) {

                            // Get session ceiling from organization member or default
                            $sessionCeiling = $organizationMembership->session_ceiling_ngn ?? 15000;

                            // Calculate session fee
                            $sessionFee = ($therapist->hourly_rate / 60) * ($request->duration_minutes ?? 60);

                            if ($sessionFee <= $sessionCeiling) {
                                // Full coverage
                                $isCorporateCovered = true;
                                $amountCovered = $sessionFee;
                                $amountChargedToUser = 0;
                            } else {
                                // Partial coverage - user pays difference
                                $isCorporateCovered = true;
                                $amountCovered = $sessionCeiling;
                                $amountChargedToUser = $sessionFee - $sessionCeiling;
                            }
                        }
                    }
                }

                // Freemium users are allowed to book — they pay the therapist rate
                // plus the config-driven platform booking fee (see PaymentController::derivePlatformFee).
                // Only premium/recovery plan holders are fee-exempt.
                // Anonymous bookings are also permitted and pay the platform fee directly.

                Log::info('Session: Booking session', [
                    'user_id' => $user ? $user->id : null,
                    'is_anonymous' => $isAnonymous,
                    'anonymous_nickname' => $request->anonymous_nickname ?? null,
                    'therapist_id' => $therapist->id,
                    'date' => $request->session_date ?? $request->scheduled_at,
                    'time' => $request->session_time ?? null,
                    'is_corporate_covered' => $isCorporateCovered,
                    'amount_covered' => $amountCovered,
                    'amount_charged_to_user' => $amountChargedToUser,
                ]);

                $sessionDuration = $request->duration_minutes ?? 60;

                // Validate therapist is available
                $scheduledAt = $request->scheduled_at
                    ? Carbon::parse($request->scheduled_at)
                    : Carbon::parse(($request->session_date ?? now()->format('Y-m-d')).' '.($request->session_time ?? now()->format('H:i')));

                $quotaMax = null;
                $periodStart = null;
                $periodEnd = null;

                // Skip subscription lookup for anonymous bookings
                if (! $isAnonymous) {
                    try {
                        $paySub = \App\Models\Payment\Subscription::where('user_id', $user->id)
                            ->active()
                            ->with('plan')
                            ->orderByDesc('expires_at')
                            ->first();
                        if ($paySub && $paySub->plan) {
                            $quotaMax = (int) (data_get($paySub->plan->features, 'max_sessions') ?? 0);
                            $periodStart = now()->startOfMonth();
                            $periodEnd = $paySub->expires_at;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Payment\Subscription lookup failed, using fallback', ['error' => $e->getMessage()]);
                    }
                }

                if (! $isAnonymous && ! $quotaMax) {
                    $legacySub = \App\Models\Subscription::where('user_id', $user->id)
                        ->where('status', 'active')
                        ->where(function ($q) use ($scheduledAt) {
                            $q->where('current_period_start', '<=', $scheduledAt)
                                ->where('current_period_end', '>=', $scheduledAt);
                        })
                        ->with('plan')
                        ->orderByDesc('current_period_end')
                        ->first();
                    if ($legacySub && $legacySub->plan) {
                        $quotaMax = (int) ($legacySub->plan->max_sessions ?? 0);
                        $periodStart = $legacySub->current_period_start;
                        $periodEnd = $legacySub->current_period_end;
                    }
                }

                if (! $quotaMax) {
                    $quotaMax = (int) (optional(\App\Models\Setting::where('key', 'max_sessions_default')->first())->value ?? 0);
                }

                // Validate quota for all bookings (including anonymous)
                if ($this->quotaService) {
                    if ($isAnonymous) {
                        // Use QuotaService for comprehensive anonymous quota validation
                        $anonymousId = $request->input('anonymous_id', session()->getId());
                        $quotaInfo = $this->quotaService->getQuotaInfo(null, $anonymousId);

                        // Check for quota violations
                        if (! empty($quotaInfo['warnings'])) {
                            $criticalWarnings = array_filter($quotaInfo['warnings'], function ($warning) {
                                return $warning['level'] === 'critical';
                            });

                            if (! empty($criticalWarnings)) {
                                $firstCritical = reset($criticalWarnings);
                                throw new Exception($firstCritical['message'].'. Please create an account to continue booking sessions.');
                            }
                        }

                        // Store quota info for later increment
                        $anonymousSessionData = [
                            'quota_info' => $quotaInfo,
                            'anonymous_id' => $anonymousId,
                        ];
                    } else {
                        // Use QuotaService for authenticated user quota validation
                        $quotaInfo = $this->quotaService->getQuotaInfo($user);

                        // Check for quota violations
                        if (! empty($quotaInfo['warnings'])) {
                            $criticalWarnings = array_filter($quotaInfo['warnings'], function ($warning) {
                                return $warning['level'] === 'critical';
                            });

                            if (! empty($criticalWarnings)) {
                                $firstCritical = reset($criticalWarnings);
                                throw new Exception($firstCritical['message']);
                            }
                        }
                    }
                } else {
                    // Quota validation handled above with QuotaService
                }

                $isAvailable = true;
                if (class_exists(\App\Models\TherapistAvailability::class)) {
                    $day = (int) $scheduledAt->format('w');
                    $dateStr = $scheduledAt->format('Y-m-d');
                    $timeStr = $scheduledAt->format('H:i');
                    $availabilities = \App\Models\TherapistAvailability::where('therapist_id', $therapist->user_id)
                        ->where(function ($q) use ($day, $dateStr) {
                            $q->where('is_recurring', true)->where('day_of_week', $day)
                                ->orWhere(function ($q2) use ($dateStr) {
                                    $q2->where('specific_date', $dateStr);
                                });
                        })
                        ->get();
                    $isAvailable = $availabilities->isEmpty() ? true : $availabilities->contains(function ($slot) use ($timeStr) {
                        return $timeStr >= $slot->start_time && $timeStr < $slot->end_time;
                    });
                }
                if (! $isAvailable) {
                    throw new Exception('Selected time slot is not available');
                }

                // E2: Overlap-aware double-booking prevention (FIX 11)
                // Check: existing_start < requested_end AND existing_end > requested_start
                $requestedEnd = $scheduledAt->copy()->addMinutes($sessionDuration);

                $conflictingSession = \App\Models\TherapySession::where('therapist_id', $therapist->user_id)
                    ->whereIn('status', ['booked', 'pending_confirmation', 'in_progress', 'scheduled'])
                    ->where('scheduled_at', '<', $requestedEnd)
                    ->whereRaw('DATE_ADD(scheduled_at, INTERVAL COALESCE(duration_minutes, 60) MINUTE) > ?', [$scheduledAt])
                    ->lockForUpdate()
                    ->first(['scheduled_at', 'duration_minutes']);

                if ($conflictingSession) {
                    $conflictEnd = $conflictingSession->scheduled_at->copy()
                        ->addMinutes($conflictingSession->duration_minutes ?? 60);
                    // Suggest the next open slot immediately after the conflicting session ends
                    $nextSlot = $conflictEnd->copy()->ceilMinutes(30)->toDateTimeString();
                    throw new \App\Exceptions\BookingConflictException(
                        'This time slot overlaps with an existing booking. Next available slot: ' . $nextSlot,
                        $nextSlot
                    );
                }

                // Calculate session fee
                $hourlyRate = $therapist->hourly_rate;
                $sessionFee = ($hourlyRate / 60) * $sessionDuration;

                // Handle corporate coverage
                if ($isCorporateCovered) {
                    // Update session fee to reflect what user actually pays
                    $sessionFee = $amountChargedToUser;
                }

                // Apply promotional code discount (authenticated, non-corporate sessions only)
                $promoDiscount = 0.0;
                $promoCodeId   = null;
                if (! $isAnonymous && $user && $request->filled('promo_code')) {
                    $currency = 'NGN'; // default; adjust if multi-currency context is available
                    $promoResult = app(\App\Services\PromotionalCodeService::class)->validate(
                        $request->promo_code,
                        $user->id,
                        $currency,
                        $sessionFee,
                        'session'
                    );
                    if (! $promoResult['valid']) {
                        return response()->json([
                            'success' => false,
                            'message' => $promoResult['message'],
                        ], 422);
                    }
                    $promoDiscount = $promoResult['discount_amount'];
                    $promoCodeId   = $promoResult['code']->id;
                    // Reduce effective session fee by the discount
                    $sessionFee = max(0, $sessionFee - $promoDiscount);
                }

                // Calculate booking fee for authenticated, non-corporate users
                $bookingFeeResult = ['fee' => 0.0, 'waived' => true, 'waiver_reason' => null];
                if (!$isAnonymous && $user) {
                    $bookingFeeService = app(\App\Services\BookingFeeService::class);
                    $bookingFeeResult = $bookingFeeService->calculate($user, $currency ?? 'NGN');
                }
                $bookingFee = $bookingFeeResult['fee'];

                // Create session record with pending confirmation status
                $sessionData = [
                    'therapist_id'              => $therapist->user_id,
                    'session_type'              => $request->session_type ?? 'video',
                    'scheduled_at'              => $scheduledAt,
                    'duration_minutes'          => $sessionDuration,
                    'booking_notes'             => $request->notes ?? null,
                    'status'                    => 'pending_confirmation',
                    'session_rate'              => $sessionFee,
                    'payment_status'            => $isCorporateCovered && $amountChargedToUser === 0 ? 'covered' : 'pending',
                    'is_anonymous'              => $isAnonymous,
                    'anonymous_fingerprint'     => $anonymousFingerprint,
                    'anonymous_nickname'        => $request->anonymous_nickname ?? null,
                    'promo_code_id'             => $promoCodeId,
                    'promo_discount_amount'     => $promoDiscount > 0 ? $promoDiscount : null,
                    'booking_fee_amount'        => $bookingFee,
                    'booking_fee_waived'        => $bookingFeeResult['waived'],
                    'booking_fee_waiver_reason' => $bookingFeeResult['waiver_reason'],
                ];

                // Only set patient_id for authenticated users
                if (! $isAnonymous && $user) {
                    $sessionData['patient_id'] = $user->id;
                }

                $session = \App\Models\TherapySession::create($sessionData);

                // Redeem promotional code now that the session record exists
                if ($promoCodeId && $promoDiscount > 0) {
                    $promoCode = \App\Models\PromotionalCode::find($promoCodeId);
                    if ($promoCode) {
                        app(\App\Services\PromotionalCodeService::class)->redeem(
                            $promoCode,
                            $user->id,
                            $promoDiscount,
                            $session->id
                        );
                    }
                }

                // Increment quota usage for authenticated users
                if (! $isAnonymous && $user && $this->quotaService) {
                    $this->quotaService->incrementQuotaUsage($user);
                }

                if (class_exists(\App\Jobs\SendSessionReminder::class)) {
                    try {
                        $delay = \Carbon\Carbon::parse($session->scheduled_at)->copy()->subMinutes(15);
                        if ($delay->isFuture()) {
                            SendSessionReminder::dispatch($session)->delay($delay);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Session reminder dispatch skipped', ['error' => $e->getMessage()]);
                    }
                }

                // Add participants if any (Group Therapy)
                if ($request->has('participants') && is_array($request->participants)) {
                    foreach ($request->participants as $participantId) {
                        \App\Models\SessionParticipant::create([
                            'session_id' => $session->id,
                            'user_id' => $participantId,
                            'role' => 'patient', // Default role, can be adjusted if needed
                            'status' => 'invited',
                        ]);
                    }
                }

                // Handle corporate coverage after session creation
                if ($isCorporateCovered && $organizationMembership) {
                    // Create organization session log for audit trail
                    OrganizationSessionLog::create([
                        'organization_id' => $organizationMembership->organization_id,
                        'member_id' => $organizationMembership->id,
                        'session_id' => $session->id,
                        'amount_covered' => $amountCovered,
                        'amount_charged_to_user' => $amountChargedToUser,
                    ]);

                    // Increment sessions used this month
                    $organizationMembership->increment('sessions_used_this_month');
                }

                Log::info('Session: Session created', ['session_id' => $session->id]);
                try {
                    $dateTimeStr = Carbon::parse($session->scheduled_at)->toDayDateTimeString();
                    $linkBase = rtrim(config('frontend.url', config('app.url')), '/');
                    $link = $linkBase.'/session/'.$session->uuid;
                    $queueConn = config('queue.default');
                    $forceSend = app()->environment('testing');

                    // Use different email templates based on session status
                    $isPendingConfirmation = $session->status === 'pending_confirmation';

                    // Send email to patient only for authenticated users
                    if (! $isAnonymous && config('notifications.booking.send_to_patient', true)) {
                        $mailableClass = $isPendingConfirmation
                            ? AppointmentPendingConfirmation::class
                            : AppointmentBookingConfirmation::class;

                        $mailable = new $mailableClass(
                            $user->full_name,
                            $therapist->user->full_name,
                            $dateTimeStr,
                            $link
                        );
                        if ($forceSend || $queueConn === 'sync') {
                            Mail::to($user->email)->send($mailable);
                        } else {
                            Mail::to($user->email)->queue($mailable);
                        }
                    }

                    // For therapists, always send confirmation notification
                    if (config('notifications.booking.send_to_therapist', true)) {
                        $mailableClass = $isPendingConfirmation
                            ? TherapistSessionRequestNotification::class
                            : AppointmentBookingConfirmation::class;

                        // Use anonymous nickname for anonymous bookings, otherwise use user full name
                        $customerName = $isAnonymous ? ($request->anonymous_nickname ?? 'Anonymous User') : $user->full_name;

                        $mailableT = new $mailableClass(
                            $customerName,
                            $therapist->user->full_name,
                            $dateTimeStr,
                            $link
                        );
                        if ($forceSend || $queueConn === 'sync') {
                            Mail::to($therapist->user->email)->send($mailableT);
                        } else {
                            Mail::to($therapist->user->email)->queue($mailableT);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Session booking email skipped', ['error' => $e->getMessage()]);
                }

                if (env('PAYMENTS_ENABLED', false)) {
                    // Handle anonymous bookings with payment requirement
                    if ($isAnonymous) {
                        // Increment quota usage with QuotaService
                        if (isset($anonymousSessionData) && $this->quotaService) {
                            $this->quotaService->incrementQuotaUsage(
                                null,
                                $anonymousSessionData['anonymous_id']
                            );
                        }

                        // Create payment for anonymous booking
                        $payment = \App\Models\Payment::create([
                            'user_id' => null, // Anonymous booking
                            'session_id' => $session->id,
                            'amount' => $sessionFee,
                            'currency' => 'NGN',
                            'payment_type' => 'anonymous_session_booking',
                            'description' => "Anonymous therapy session with {$therapist->user->full_name}",
                            'status' => 'draft',
                            'payment_status' => 'pending',
                            'metadata' => [
                                'is_anonymous' => true,
                                'anonymous_nickname' => $request->anonymous_nickname ?? null,
                            ],
                        ]);

                        Log::info('Session: Anonymous payment created', ['payment_id' => $payment->id]);

                        $paymentResult = $this->paymentProcessor->processPayment($payment, [
                            'customer_name' => $request->payment_name ?? $request->anonymous_nickname ?? 'Anonymous User',
                            'customer_email' => $request->anonymous_email,
                            'session_id' => $session->id,
                            'therapist_id' => $therapist->id,
                            'is_anonymous' => true,
                        ]);

                        Log::info('Session: Anonymous booking completed', [
                            'session_id' => $session->id,
                            'payment_id' => $payment->id,
                        ]);

                        return response()->json([
                            'success' => true,
                            'message' => 'Session booked successfully. Payment required to confirm.',
                            'data' => [
                                'session_id' => $session->id,
                                'payment_id' => $payment->id,
                                'authorization_url' => $paymentResult['authorization_url'],
                                'payment_reference' => $paymentResult['reference'],
                                'session_fee' => $this->currencyService->format($sessionFee, 'NGN'),
                                'session_fee_numeric' => $sessionFee,
                                'scheduled_date' => $session->scheduled_at->format('Y-m-d'),
                                'scheduled_time' => $session->scheduled_at->format('H:i'),
                                'therapist_name' => $therapist->user->full_name,
                                'status' => 'pending_payment',
                                'is_anonymous' => true,
                                'anonymous_nickname' => $request->anonymous_nickname ?? null,
                                'quota_remaining' => isset($anonymousSessionData) ?
                                    max(0, $anonymousSessionData['quota_limit'] - ($anonymousSessionData['current_count'] + 1)) : null,
                            ],
                        ], 201);
                    }

                    // Handle corporate coverage - skip payment creation if fully covered
                    if ($isCorporateCovered && $amountChargedToUser === 0) {
                        // No payment needed - session is fully covered
                        Log::info('Session: Corporate session fully covered', [
                            'session_id' => $session->id,
                            'organization_id' => $organizationMembership->organization_id,
                            'amount_covered' => $amountCovered,
                        ]);

                        return response()->json([
                            'success' => true,
                            'message' => 'Session booked successfully',
                            'data' => [
                                'session_id' => $session->id,
                                'scheduled_date' => $session->scheduled_at->format('Y-m-d'),
                                'scheduled_time' => $session->scheduled_at->format('H:i'),
                                'therapist_name' => $therapist->user->full_name,
                                'status' => 'scheduled',
                                'is_corporate_covered' => true,
                                'amount_covered' => $amountCovered,
                                'sessions_remaining' => max(0, $organizationMembership->sessions_limit - $organizationMembership->sessions_used_this_month),
                            ],
                        ], 201);
                    }

                    // Create payment only if user needs to pay
                    if ($isCorporateCovered && $amountChargedToUser > 0) {
                        // Partial coverage - user pays difference
                        $payment = \App\Models\Payment::create([
                            'user_id' => $user->id,
                            'session_id' => $session->id,
                            'amount' => $sessionFee,
                            'currency' => 'NGN',
                            'payment_type' => 'session_booking',
                            'description' => "Therapy session with {$therapist->user->full_name} (partially covered by {$organizationMembership->organization->name})",
                            'status' => 'draft',
                            'payment_status' => 'pending',
                            'metadata' => [
                                'is_corporate_covered' => true,
                                'amount_covered' => $amountCovered,
                                'organization_id' => $organizationMembership->organization_id,
                            ],
                        ]);
                    } else {
                        // Standard payment (no corporate coverage)
                        $payment = \App\Models\Payment::create([
                            'user_id' => $user->id,
                            'session_id' => $session->id,
                            'amount' => $sessionFee,
                            'currency' => 'NGN',
                            'payment_type' => 'session_booking',
                            'description' => "Therapy session with {$therapist->user->full_name}",
                            'status' => 'draft',
                            'payment_status' => 'pending',
                        ]);
                    }

                    Log::info('Session: Payment created', ['payment_id' => $payment->id]);

                    $paymentResult = $this->paymentProcessor->processPayment($payment, [
                        'customer_name' => $user->full_name,
                        'session_id' => $session->id,
                        'therapist_id' => $therapist->id,
                    ]);

                    Log::info('Session: Booking completed', [
                        'session_id' => $session->id,
                        'payment_id' => $payment->id,
                    ]);

                    // Update response to include corporate coverage info
                    $responseData = [
                        'session_id' => $session->id,
                        'payment_id' => $payment->id,
                        'authorization_url' => $paymentResult['authorization_url'],
                        'payment_reference' => $paymentResult['reference'],
                        'session_fee' => $this->currencyService->format($sessionFee, 'NGN'),
                        'session_fee_numeric' => $sessionFee,
                        'scheduled_date' => $session->scheduled_at->format('Y-m-d'),
                        'scheduled_time' => $session->scheduled_at->format('H:i'),
                        'therapist_name' => $therapist->user->full_name,
                        'status' => 'pending_payment',
                    ];

                    // Add corporate coverage info if applicable
                    if ($isCorporateCovered) {
                        $responseData['is_corporate_covered'] = true;
                        $responseData['amount_covered'] = $amountCovered;
                        $responseData['sessions_remaining'] = max(0, $organizationMembership->sessions_limit - $organizationMembership->sessions_used_this_month);
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'Session booked successfully',
                        'data' => $responseData,
                    ], 201);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Session booked successfully',
                    'data' => $session,
                ], 201);
            });
        } catch (\App\Exceptions\BookingConflictException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'next_available_slot' => $e->nextAvailableSlot,
            ], 409);
        } catch (Exception $e) {
            Log::error('Session: Booking failed', [
                'user_id' => Auth::id(),
                'is_anonymous' => $isAnonymous,
                'anonymous_nickname' => $request->anonymous_nickname ?? null,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to book session: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get user's scheduled sessions
     *
     * GET /api/v1/sessions
     */
    public function getUserSessions(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $status = $request->get('status');
            $perPage = $request->get('per_page', 20);

            Log::info('Session: Get user sessions', [
                'user_id' => $user->id,
                'status' => $status,
            ]);

            $sessions = $this->therapyRepository->getPatientSessions($user->id, [
                'status' => $status,
                'per_page' => $perPage,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sessions retrieved',
                'data' => [
                    'sessions' => $sessions->map(fn ($session) => [
                        'id' => $session->id,
                        'therapist_id' => $session->therapist_id,
                        'therapist_name' => $session->therapist->full_name,
                        'session_type' => $session->session_type,
                        'scheduled_date' => $session->scheduled_at->format('Y-m-d'), // Using scheduled_at from repo model
                        'scheduled_time' => $session->scheduled_at->format('H:i'),
                        'duration_minutes' => $session->duration_minutes,
                        'status' => $session->status,
                        'session_fee' => $this->currencyService->format($session->session_rate, 'NGN'),
                        'session_fee_numeric' => $session->session_rate,
                        'payment_status' => $session->payment_status,
                        'notes' => $session->booking_notes,
                        'created_at' => $session->created_at,
                    ]),
                    'pagination' => [
                        'total' => $sessions->total(),
                        'count' => $sessions->count(),
                        'per_page' => $sessions->perPage(),
                        'current_page' => $sessions->currentPage(),
                        'last_page' => $sessions->lastPage(),
                    ],
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Session: Get user sessions failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sessions',
            ], 400);
        }
    }

    /**
     * Get session details
     *
     * GET /api/v1/sessions/{id}
     *
     * @param  int  $id
     */
    public function getSessionDetails($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $session = $this->therapyRepository->find($id);

            if (! $session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found',
                ], 404);
            }

            if ($session->patient_id !== $user->id && $session->therapist_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            Log::info('Session: Get details', ['session_id' => $session->id]);

            $session->load(['patient', 'therapist']); // Ensure relations are loaded

            return response()->json([
                'success' => true,
                'message' => 'Session details retrieved',
                'data' => [
                    'id' => $session->id,
                    'user' => [
                        'id' => $session->patient->id,
                        'name' => $session->patient->full_name,
                        'email' => $session->patient->email,
                    ],
                    'therapist' => [
                        'id' => $session->therapist->id,
                        'name' => $session->therapist->full_name,
                        'specialization' => $session->therapist->specialization ?? 'General',
                    ],
                    'session_type' => $session->session_type,
                    'scheduled_date' => $session->scheduled_at->format('Y-m-d'),
                    'scheduled_time' => $session->scheduled_at->format('H:i'),
                    'duration_minutes' => $session->duration_minutes,
                    'session_fee' => $this->currencyService->format($session->session_rate, 'NGN'),
                    'session_fee_numeric' => $session->session_rate,
                    'status' => $session->status,
                    'payment_status' => $session->payment_status,
                    'notes' => $session->booking_notes,
                    'meeting_link' => $session->meeting_url,
                    'created_at' => $session->created_at,
                    'updated_at' => $session->updated_at,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Session: Get details failed', [
                'session_id' => $session->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve session details',
            ], 400);
        }
    }

    /**
     * Reschedule a session
     *
     * POST /api/v1/sessions/{id}/reschedule
     *
     * @param  int  $id
     */
    public function rescheduleSession($id, RescheduleSessionRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $session = $this->therapyRepository->find($id);

            if (! $session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found',
                ], 404);
            }

            // Check if user is the owner (patient) or the therapist
            if ($session->patient_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            if ($session->status === 'completed' || $session->status === 'cancelled') {
                throw new Exception('Cannot reschedule completed or cancelled sessions');
            }

            Log::info('Session: Rescheduling', [
                'session_id' => $session->id,
                'new_date' => $request->new_date,
                'new_time' => $request->new_time,
            ]);

            // Check availability
            $sessionDuration = $session->duration_minutes ?? 60;
            $isAvailable = $this->therapyRepository->checkAvailability(
                $session->therapist_id,
                $request->new_date,
                $request->new_time,
                $sessionDuration,
                $session->id
            );

            if (! $isAvailable) {
                throw new Exception('New time slot is not available');
            }

            $this->therapyRepository->update($session->id, [
                'scheduled_at' => Carbon::parse($request->new_date.' '.$request->new_time),
                'status' => 'scheduled',
            ]);

            Log::info('Session: Rescheduled', ['session_id' => $session->id]);

            // Reload to get updated timestamp
            $session = $this->therapyRepository->find($session->id);

            return response()->json([
                'success' => true,
                'message' => 'Session rescheduled successfully',
                'data' => [
                    'session_id' => $session->id,
                    'new_date' => $session->scheduled_at->format('Y-m-d'),
                    'new_time' => $session->scheduled_at->format('H:i'),
                    'status' => $session->status,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Session: Reschedule failed', [
                'session_id' => $session->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reschedule session: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel a session
     *
     * POST /api/v1/sessions/{id}/cancel
     *
     * @param  int  $id
     */
    public function cancelSession($id, CancelSessionRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $session = $this->therapyRepository->find($id);

            if (! $session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found',
                ], 404);
            }

            if ($session->patient_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            if ($session->status === 'completed' || $session->status === 'cancelled') {
                throw new Exception('Session already completed or cancelled');
            }

            Log::info('Session: Cancelling', [
                'session_id' => $session->id,
                'reason' => $request->reason,
            ]);

            $now = now();
            $scheduledAt = Carbon::parse($session->scheduled_at);
            $hoursUntilSession = $now->diffInHours($scheduledAt, false);

            $refundStatus = 'not_applicable';
            $refundAmount = 0;

            if ($session->payment && $session->payment->status === 'completed') {
                if ($hoursUntilSession >= 24) {
                    // Full refund
                    $refundStatus = 'full_refund_pending';
                    $refundAmount = $session->session_rate;
                    $this->paymentProcessor->refundPayment($session->payment, $refundAmount);
                } elseif ($hoursUntilSession >= 1) {
                    // Partial refund (e.g., 50% fee)
                    $refundStatus = 'partial_refund_pending';
                    $refundAmount = $session->session_rate * 0.5; // 50% fee
                    $this->paymentProcessor->refundPayment($session->payment, $refundAmount);
                } else {
                    // No refund
                    $refundStatus = 'no_refund';
                    $refundAmount = 0;
                }
            }

            $this->therapyRepository->update($session->id, [
                'status' => 'cancelled',
                'cancellation_reason' => $request->reason,
                'cancelled_at' => now(),
            ]);

            Log::info('Session: Cancelled', [
                'session_id' => $session->id,
                'refund_status' => $refundStatus,
                'refund_amount' => $refundAmount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Session cancelled successfully',
                'data' => [
                    'session_id' => $session->id,
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'refund_status' => $refundStatus,
                    'refund_amount' => $refundAmount,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Session: Cancel failed', [
                'session_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel session: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Complete a session (therapist only)
     *
     * POST /api/v1/sessions/{id}/complete
     *
     * @param  int  $id
     */
    public function completeSession($id, CompleteSessionRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $session = $this->therapyRepository->find($id);

            if (! $session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found',
                ], 404);
            }

            if ($session->therapist_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only assigned therapist can complete session',
                ], 403);
            }

            Log::info('Session: Completing', ['session_id' => $session->id]);

            $this->therapyRepository->update($session->id, [
                'status' => 'completed',
                'ended_at' => now(),
            ]);

            // Update session notes
            if ($request->session_notes || $request->next_session_recommendation) {
                $this->therapyRepository->updateSessionNote($session->id, [
                    'therapist_id' => $user->id,
                    'session_summary' => $request->session_notes,
                    'treatment_plan' => $request->next_session_recommendation,
                ]);
            }

            Log::info('Session: Completed', ['session_id' => $session->id]);

            return response()->json([
                'success' => true,
                'message' => 'Session marked as completed',
                'data' => [
                    'session_id' => $session->id,
                    'status' => 'completed',
                    'completed_at' => now(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Session: Completion failed', [
                'session_id' => $session->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete session: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Submit session feedback
     *
     * POST /api/v1/sessions/{id}/feedback
     *
     * @param  int  $id
     */
    public function submitFeedback($id, FeedbackRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $session = $this->therapyRepository->find($id);

            if (! $session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found',
                ], 404);
            }

            if ($session->patient_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            if ($session->status !== 'completed') {
                throw new Exception('Can only submit feedback for completed sessions');
            }

            Log::info('Session: Submitting feedback', ['session_id' => $session->id]);

            $this->therapyRepository->submitFeedback($session->id, [
                'patient_id' => $user->id,
                'rating' => $request->rating,
                'comment' => $request->comment,
            ]);

            Log::info('Session: Feedback submitted', ['session_id' => $session->id]);

            return response()->json([
                'success' => true,
                'message' => 'Feedback submitted successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Session: Feedback submission failed', [
                'session_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit feedback: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Track when a participant joins the session room
     *
     * POST /api/v1/sessions/{uuid}/participant-joined
     */
    public function participantJoined($uuid, Request $request): JsonResponse
    {
        try {
            $session = \App\Models\TherapySession::where('uuid', $uuid)->first();

            if (! $session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found',
                ], 404);
            }

            $role = $request->input('role');
            $now = now();

            if ($role === 'therapist') {
                $session->therapist_joined_at = $now;
            } elseif ($role === 'patient') {
                $session->patient_joined_at = $now;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid role provided',
                ], 422);
            }

            // If BOTH are now set and session_started_at is null: set session_started_at = now()
            if ($session->therapist_joined_at && $session->patient_joined_at && ! $session->session_started_at) {
                $session->session_started_at = $now;
                $session->status = 'ongoing';
            }

            $session->save();

            return response()->json([
                'success' => true,
                'message' => 'Participant join recorded',
                'data' => [
                    'session_started_at' => $session->session_started_at,
                    'patient_joined_at' => $session->patient_joined_at,
                    'therapist_joined_at' => $session->therapist_joined_at,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Session: Participant join failed', [
                'session_uuid' => $uuid,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to record join event',
            ], 400);
        }
    }

    /**
     * Preview the booking fee for the current user before checkout.
     *
     * GET /api/v1/sessions/booking-fee-preview
     */
    public function feePreview(Request $request): JsonResponse
    {
        $request->validate([
            'therapist_id' => 'required|integer',
            'currency'     => 'nullable|in:NGN,USD',
        ]);

        /** @var \App\Models\User $user */
        $user     = Auth::user();
        $currency = $request->input('currency', 'NGN');

        $result = app(\App\Services\BookingFeeService::class)->calculate($user, $currency);

        return response()->json(['success' => true, 'data' => $result]);
    }

    // Private methods removed as they are now in repository
}
