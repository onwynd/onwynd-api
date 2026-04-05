
<?php

use App\Http\Controllers\API\V1\Admin\AdminAIChatController;
use App\Http\Controllers\API\V1\Admin\PromotionalCodeController as AdminPromotionalCodeController;
use App\Http\Controllers\API\V1\Patient\PromotionalCodeController as PatientPromotionalCodeController;
// Medicals
use App\Http\Controllers\API\V1\Admin\AdminBookingController;
use App\Http\Controllers\API\V1\Admin\AdminCenterController;
use App\Http\Controllers\API\V1\Admin\AdminInventoryController;
use App\Http\Controllers\API\V1\Admin\AdminNotificationController;
use App\Http\Controllers\API\V1\Admin\AdminRevenueController;
use App\Http\Controllers\API\V1\Admin\AdminSubscriptionListController;
use App\Http\Controllers\API\V1\Admin\AdminTherapistVerificationController;
// Gamification, Badges & Rewards
use App\Http\Controllers\API\V1\Admin\CommunityController as AdminCommunityController;
use App\Http\Controllers\API\V1\Admin\ContentController as AdminContentController;
// Auth Controllers
use App\Http\Controllers\API\V1\Admin\COOController;
use App\Http\Controllers\API\V1\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\API\V1\Admin\DashboardController as AdminDashboardController;
// Patient Controllers (Renamed from Customer)
use App\Http\Controllers\API\V1\Admin\FeedbackController;
use App\Http\Controllers\API\V1\Admin\LandingPageContentController as AdminLandingPageContentController;
use App\Http\Controllers\API\V1\Admin\MailController;
use App\Http\Controllers\API\V1\Admin\MaintenanceController as AdminMaintenanceController;
use App\Http\Controllers\API\V1\Admin\QuotaSettingController;
use App\Http\Controllers\API\V1\Admin\ReportController as AdminReportController;
use App\Http\Controllers\API\V1\Admin\ResourceController as AdminResourceController;
use App\Http\Controllers\API\V1\Admin\RoleController;
use App\Http\Controllers\API\V1\Admin\SessionController as AdminSessionController;
use App\Http\Controllers\API\V1\Admin\AuthSessionController;
use App\Http\Controllers\API\V1\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\API\V1\Admin\WhatsAppController as AdminWhatsAppController;
use App\Http\Controllers\API\V1\Admin\SubscriptionPlanController;
use App\Http\Controllers\API\V1\Admin\TherapistController as AdminTherapistController;
use App\Http\Controllers\API\V1\Admin\AdminCorporateController;
use App\Http\Controllers\API\V1\Admin\UniversityConfigController;
use App\Http\Controllers\API\V1\Admin\UserManagementController as AdminUserManagementController;
use App\Http\Controllers\API\V1\AI\CompanionDebugController;
use App\Http\Controllers\API\V1\AI\DocumentAnalysisController;
use App\Http\Controllers\API\V1\Ambassador\DashboardController as AmbassadorDashboardController;
use App\Http\Controllers\API\V1\Ambassador\PayoutController as AmbassadorPayoutController;
use App\Http\Controllers\API\V1\Ambassador\ReferralController as AmbassadorReferralController;
use App\Http\Controllers\API\V1\Analytics\ExportController;
use App\Http\Controllers\API\V1\Analytics\MetricsController;
use App\Http\Controllers\API\V1\Analytics\ReportController as AnalyticsReportController;
use App\Http\Controllers\API\V1\Auth\AuthController;
use App\Http\Controllers\API\V1\Auth\DeviceTokenController;
use App\Http\Controllers\API\V1\Auth\EmailVerificationController;
use App\Http\Controllers\API\V1\Auth\PasswordResetController;
use App\Http\Controllers\API\V1\Auth\PatientInviteAcceptController;
use App\Http\Controllers\API\V1\Therapist\PatientInviteController as TherapistPatientInviteController;
use App\Http\Controllers\API\V1\Chat\CategoryController as ChatCategoryController;
use App\Http\Controllers\API\V1\ConfigController;
// Therapist Controllers
use App\Http\Controllers\API\V1\Content\TestimonialController;
use App\Http\Controllers\API\V1\Documents\SecureDocumentController;
use App\Http\Controllers\API\V1\Employee\DashboardController as EmployeeDashboardController;
use App\Http\Controllers\API\V1\Employee\TaskController as EmployeeTaskController;
use App\Http\Controllers\API\V1\Employee\TimeSheetController as EmployeeTimeSheetController;
use App\Http\Controllers\API\V1\Finance\DashboardController as FinanceDashboardController;
// Admin Controllers
use App\Http\Controllers\API\V1\Finance\InvoiceController as FinanceInvoiceController;
use App\Http\Controllers\API\V1\Finance\PayoutController as FinancePayoutController;
use App\Http\Controllers\API\V1\Finance\RevenueController as FinanceRevenueController;
use App\Http\Controllers\API\V1\Gamification\GamificationController;
use App\Http\Controllers\API\V1\HR\DashboardController as HRDashboardController;
use App\Http\Controllers\API\V1\HR\EmployeeController as HREmployeeController;
use App\Http\Controllers\API\V1\HR\LeaveController as HRLeaveController;
use App\Http\Controllers\API\V1\HR\PayrollController as HRPayrollController;
use App\Http\Controllers\API\V1\Institutional\CorporateController;
use App\Http\Controllers\API\V1\Institutional\DashboardController as InstitutionalDashboardController;
use App\Http\Controllers\API\V1\Institutional\InstitutionalDocumentController;
use App\Http\Controllers\API\V1\Institutional\InviteController as InstitutionalInviteController;
use App\Http\Controllers\API\V1\Institutional\MemberController as InstitutionalMemberController;
use App\Http\Controllers\API\V1\Institutional\OrganizationController as InstitutionalOrganizationController;
use App\Http\Controllers\API\V1\Institutional\ReferralController as InstitutionalReferralController;
use App\Http\Controllers\API\V1\Institutional\ReportController as InstitutionalReportController;
use App\Http\Controllers\API\V1\Institutional\SubscriptionController as InstitutionalSubscriptionController;
use App\Http\Controllers\API\V1\Institutional\UniversityController;
use App\Http\Controllers\API\V1\JobApplicationController;
use App\Http\Controllers\API\V1\JobPostingController;
use App\Http\Controllers\API\V1\KnowledgeBase\KnowledgeBaseController;
use App\Http\Controllers\API\V1\Manager\DashboardController as ManagerDashboardController;
use App\Http\Controllers\API\V1\Manager\InventoryController as ManagerInventoryController;
use App\Http\Controllers\API\V1\Manager\ReportController as ManagerReportController;
use App\Http\Controllers\API\V1\Manager\ScheduleController as ManagerScheduleController;
use App\Http\Controllers\API\V1\Manager\TeamController as ManagerTeamController;
// Manager Controllers
use App\Http\Controllers\API\V1\Marketing\AmbassadorSettingsController;
use App\Http\Controllers\API\V1\Marketing\AnalyticsController as MarketingAnalyticsController;
use App\Http\Controllers\API\V1\Marketing\CampaignController as MarketingCampaignController;
use App\Http\Controllers\API\V1\Marketing\DashboardController as MarketingDashboardController;
use App\Http\Controllers\API\V1\Marketing\MarketingBroadcastController;
// Support Controllers
use App\Http\Controllers\API\V1\Marketing\MarketingEventController;
use App\Http\Controllers\API\V1\Marketing\NewsletterController as MarketingNewsletterController;
use App\Http\Controllers\API\V1\Medical\MedicationLogController;
// Sales Controllers
use App\Http\Controllers\API\V1\Medical\PrescriptionController;
use App\Http\Controllers\API\V1\OnboardingController;
use App\Http\Controllers\API\V1\Patient\AssessmentController as PatientAssessmentController;
use App\Http\Controllers\API\V1\Patient\ChatController as PatientChatController;
// Marketing Controllers
use App\Http\Controllers\API\V1\Patient\CommunityController as PatientCommunityController;
use App\Http\Controllers\API\V1\Patient\CommunityDirectoryController as PatientCommunityDirectoryController;
use App\Http\Controllers\API\V1\Patient\CourseCatalogController as PatientCourseCatalogController;
use App\Http\Controllers\API\V1\Patient\CrisisController as PatientCrisisController;
use App\Http\Controllers\API\V1\Patient\DashboardController as PatientDashboardController;
use App\Http\Controllers\API\V1\Patient\FavoriteController as PatientFavoriteController;
use App\Http\Controllers\API\V1\Patient\HabitController as PatientHabitController;
use App\Http\Controllers\API\V1\Patient\HelpController as PatientHelpController;
// Finance Controllers
use App\Http\Controllers\API\V1\Patient\JournalController as PatientJournalController;
use App\Http\Controllers\API\V1\Patient\MindfulnessController as PatientMindfulnessController;
use App\Http\Controllers\API\V1\Patient\MoodController as PatientMoodController;
use App\Http\Controllers\API\V1\Patient\NotificationController as PatientNotificationController;
// Secretary Controllers
use App\Http\Controllers\API\V1\Patient\OnboardingController as PatientOnboardingController;
use App\Http\Controllers\API\V1\Patient\PaymentController as PatientPaymentController;
use App\Http\Controllers\API\V1\Patient\ProfileController as PatientProfileController;
use App\Http\Controllers\API\V1\Patient\ResourceController as PatientResourceController;
use App\Http\Controllers\API\V1\Patient\SearchController as PatientSearchController;
use App\Http\Controllers\API\V1\Patient\SessionController as PatientSessionController;
use App\Http\Controllers\API\V1\Patient\SettingsController as PatientSettingsController;
use App\Http\Controllers\API\V1\Patient\SleepController as PatientSleepController;
use App\Http\Controllers\API\V1\Patient\StressController as PatientStressController;
use App\Http\Controllers\API\V1\Patient\SubscriptionController as PatientSubscriptionController;
use App\Http\Controllers\API\V1\Patient\TherapistController as PatientTherapistController;
use App\Http\Controllers\API\V1\Payment\DodoPaymentsController;
use App\Http\Controllers\API\V1\Payment\FlutterwaveController;
use App\Http\Controllers\API\V1\Payment\KlumpController;
use App\Http\Controllers\API\V1\Payment\PaymentController as PaymentControllerV1;
use App\Http\Controllers\API\V1\Payment\PaystackController;
use App\Http\Controllers\API\V1\Payment\StripeController;
// Partner Controllers

// Institutional Controllers
use App\Http\Controllers\API\V1\Payment\SubscriptionController as PaymentSubscriptionController;
use App\Http\Controllers\API\V1\Payment\WebhookController;
use App\Http\Controllers\API\V1\PhysicalCenter\BookingController as CenterBookingController;
use App\Http\Controllers\API\V1\PhysicalCenter\CenterController;
use App\Http\Controllers\API\V1\PhysicalCenter\CheckInController;
use App\Http\Controllers\API\V1\PhysicalCenter\InventoryController as CenterInventoryController;
use App\Http\Controllers\API\V1\ProductManager\MaintenanceController as ProductManagerMaintenanceController;
use App\Http\Controllers\API\V1\QuotaController;
use App\Http\Controllers\API\V1\Sales\DashboardController as SalesDashboardController;
// Physical Center Controllers
use App\Http\Controllers\API\V1\Sales\LeadController as SalesLeadController;
use App\Http\Controllers\API\V1\Sales\PipelineController as SalesPipelineController;
use App\Http\Controllers\API\V1\Sales\SalesNotificationController;
use App\Http\Controllers\API\V1\Secretary\AppointmentController as SecretaryAppointmentController;
use App\Http\Controllers\API\V1\Secretary\DashboardController as SecretaryDashboardController;
// Payment Controllers
use App\Http\Controllers\API\V1\Secretary\VisitorController as SecretaryVisitorController;
use App\Http\Controllers\API\V1\Support\ChatController as SupportChatController;
use App\Http\Controllers\API\V1\Support\DashboardController as SupportDashboardController;
use App\Http\Controllers\API\V1\Support\TicketController as SupportTicketController;
use App\Http\Controllers\API\V1\SystemController;
// Knowledge Base
use App\Http\Controllers\API\V1\Tech\MaintenanceController as TechMaintenanceController;
// Analytics Controllers
use App\Http\Controllers\API\V1\Therapist\AvailabilityController as TherapistAvailabilityController;
use App\Http\Controllers\API\V1\Therapist\DashboardController as TherapistDashboardController;
use App\Http\Controllers\API\V1\Therapist\EarningsController as TherapistEarningsController;
// Content Controllers
use App\Http\Controllers\API\V1\Therapist\PatientController as TherapistPatientController;
// use App\Http\Controllers\API\V1\ReviewController;
use App\Http\Controllers\API\V1\Therapist\SessionController as TherapistSessionController;
use App\Http\Controllers\API\V1\Therapist\TherapistProfileController;
use App\Http\Controllers\API\V1\Therapy\ChatController;
use App\Http\Controllers\ClinicalAdvisor\CommunicationController;
use App\Http\Controllers\ClinicalAdvisor\SessionReviewController;
use App\Http\Controllers\API\V1\Admin\SessionAuditController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ AI Audit Agent webhooks (no Sanctum auth ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â verified by AUDIT_AGENT_SECRET header) ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
    Route::prefix('internal/session-audit')->group(function () {
        Route::post('segment',  [SessionAuditController::class, 'segment']);
        Route::post('complete', [SessionAuditController::class, 'complete']);
    });

    // Public, read-only content (no auth)
    Route::prefix('public')->group(function () {
        Route::get('therapist-terms', [\App\Http\Controllers\API\V1\PublicContentController::class, 'therapistTerms']);
        Route::get('commission', [\App\Http\Controllers\API\V1\PublicContentController::class, 'commission']);

        // Waitlist ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â public signup (throttled to prevent spam)
        Route::post('waitlist', [\App\Http\Controllers\API\V1\WaitlistController::class, 'submit'])->middleware('throttle:5,1');

        // Security ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â vulnerability disclosure (throttled)
        Route::post('security/vulnerability-report', [\App\Http\Controllers\API\V1\VulnerabilityReportController::class, 'submit'])->middleware('throttle:3,1');

        // Analytics beacon ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â receives frontend page-view/event data (high-throttle, optional auth)
        Route::post('analytics/track', [\App\Http\Controllers\API\V1\AnalyticsBeaconController::class, 'track'])->middleware('throttle:120,1');
    });
    // Public routes
    Route::get('config', [ConfigController::class, 'index']);
    Route::get('config/ip-protection', [ConfigController::class, 'ipProtection']);
    Route::post('config/ip-protection/log', [\App\Http\Controllers\API\V1\Admin\IPProtectionController::class, 'logAttempt'])->middleware('throttle:10,1');
    Route::get('system/status', [SystemController::class, 'status']);
    Route::get('health', function () {
        return response()->json(['status' => 'healthy', 'timestamp' => now()]);
    });
    Route::get('content/testimonials', [TestimonialController::class, 'index']);
    Route::get('physical-centers', [CenterController::class, 'index']);
    Route::get('physical-centers/services', [CenterController::class, 'services']);
    // Static lookup routes MUST be before {uuid} to avoid being matched as a param
    Route::get('physical-centers/cities', [CenterController::class, 'cities']);
    Route::get('physical-centers/states', [CenterController::class, 'states']);
    Route::get('physical-centers/{uuid}', [CenterController::class, 'show']);
    Route::get('physical-centers/{uuid}/services', [CenterController::class, 'centerServices']);

    // Public editorial routes (used by marketing site and public editorial pages)
    Route::prefix('editorial')->group(function () {
        Route::get('posts', [\App\Http\Controllers\API\V1\EditorialPostController::class, 'index']);
        Route::get('posts/featured', [\App\Http\Controllers\API\V1\EditorialPostController::class, 'featured']);
        Route::get('categories', [\App\Http\Controllers\API\V1\EditorialPostController::class, 'categories']);
        Route::post('posts/{uuid}/view', [\App\Http\Controllers\API\V1\EditorialPostController::class, 'incrementView']);
        Route::get('posts/{uuid}/related', [\App\Http\Controllers\API\V1\EditorialPostController::class, 'related']);
        Route::get('posts/{slug}', [\App\Http\Controllers\API\V1\EditorialPostController::class, 'show']);
    });

    // Public therapist discovery (used by /therapist-booking public page)
    Route::get('therapists/specializations', [\App\Http\Controllers\API\V1\Patient\TherapistController::class, 'specializations']);
    Route::get('therapists/languages', [\App\Http\Controllers\API\V1\Patient\TherapistController::class, 'languages']);
    Route::get('therapists/available-now', [\App\Http\Controllers\API\V1\Patient\TherapistController::class, 'availableNow']);
    Route::get('therapists', [\App\Http\Controllers\API\V1\Patient\TherapistController::class, 'index']);
    Route::get('therapists/{id}', [\App\Http\Controllers\API\V1\Patient\TherapistController::class, 'show']);
    Route::get('therapists/{id}/availability', [\App\Http\Controllers\API\V1\Patient\TherapistController::class, 'availability']);
    Route::get('therapists/{id}/reviews', [\App\Http\Controllers\API\V1\Patient\TherapistController::class, 'reviews']);

    // Public contact endpoints used by marketing site
    Route::post('contact/submit', [\App\Http\Controllers\API\V1\ContactController::class, 'submit']);
    Route::post('demo-requests', [\App\Http\Controllers\API\V1\DemoRequestController::class, 'store']);
    Route::get('contact/info', [\App\Http\Controllers\API\V1\ContactController::class, 'info']);
    Route::post('contact/newsletter/subscribe', [\App\Http\Controllers\API\V1\ContactController::class, 'subscribeNewsletter']);

    // Public feedback endpoint ? available to all users (guests + any role).
    // The controller already handles Auth::check() for the user_id.
    Route::post('feedback', [\App\Http\Controllers\API\V1\Patient\HelpController::class, 'feedback']);

    // Marketing newsletter routes (public subscribe/confirm/unsubscribe-token)
    Route::prefix('marketing/newsletter')->group(function () {
        Route::post('subscribe', [MarketingNewsletterController::class, 'subscribe']);
        Route::get('confirm/{token}', [MarketingNewsletterController::class, 'confirm']);
        Route::get('unsubscribe/{token}', [MarketingNewsletterController::class, 'unsubscribeToken']);
    });
    // Public marketing ambassador settings (used by marketing web pages)
    Route::get('marketing/ambassador-settings', [AmbassadorSettingsController::class, 'index']);
    // Public chat categories for marketing/demo chat shell
    Route::get('chat/categories', [ChatCategoryController::class, 'index']);
    // Public pricing aliases for marketing site
    Route::prefix('pricing')->group(function () {
        Route::get('plans', [SubscriptionPlanController::class, 'index']);
    });

    // Public stats (no auth required - Feature 7)
    Route::get('stats/public', [\App\Http\Controllers\API\V1\PublicStatsController::class, 'stats'])->name('stats.public');

    // Public corporate pricing (no auth required - shown on marketing page)
    Route::get('corporate/pricing', [CorporateController::class, 'pricing'])->name('corporate.pricing');

    // Public assessment catalog used by the patient MVP before authentication.
    Route::get('assessments', [PatientAssessmentController::class, 'index']);
    Route::get('assessments/questions', [PatientAssessmentController::class, 'questions']);
    Route::get('assessments/categories', [PatientAssessmentController::class, 'categories']);
    Route::get('assessments/{id}', [PatientAssessmentController::class, 'show']);

    // Employee invite acceptance ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â public but optionally authenticated
    Route::get('invites/{token}', [InstitutionalInviteController::class, 'show'])
        ->middleware('throttle:30,1')
        ->name('invites.show');
    Route::post('invites/{token}/accept', [InstitutionalInviteController::class, 'accept'])
        ->middleware('throttle:20,1')
        ->name('invites.accept');

    // Paystack public init alias (no auth ÃƒÆ’Ã‚Â¯Ãƒâ€šÃ‚Â¿Ãƒâ€šÃ‚Â½ legacy widget compatibility only)
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('payments/paystack/initialize', [PaystackController::class, 'initialize'])->name('payments.paystack.initialize');
    });

    // Career Routes
    Route::get('careers/jobs', [JobPostingController::class, 'index']);
    Route::get('careers/jobs/{slug}', [JobPostingController::class, 'show']);
    Route::post('careers/jobs/{slug}/apply', [JobApplicationController::class, 'apply']);

    // Map Routes (public read, auth for agent location update)
    Route::get('map/data', [\App\Http\Controllers\API\V1\MapController::class, 'data']);

    // Location Routes (public)
    Route::prefix('locations')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\V1\LocationController::class, 'index']);
        Route::get('countries', [\App\Http\Controllers\API\V1\LocationController::class, 'countries']);
        Route::get('nigeria/states', [\App\Http\Controllers\API\V1\LocationController::class, 'nigeriaStates']);
        Route::get('{id}/children', [\App\Http\Controllers\API\V1\LocationController::class, 'children']);
    });

    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::get('exchange', [AuthController::class, 'exchange']);
        Route::get('therapist-invite/{token}', [\App\Http\Controllers\API\V1\Auth\TherapistInviteAcceptController::class, 'show']);
        Route::get('patient-invite/{token}', [PatientInviteAcceptController::class, 'show']);
        Route::post('firebase', [\App\Http\Controllers\API\V1\Auth\FirebaseAuthController::class, 'authenticate']);
        Route::post('firebase/two-factor/challenge', [\App\Http\Controllers\API\V1\Auth\FirebaseAuthController::class, 'twoFactorChallenge']);
        Route::post('forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);
        Route::post('reset-password', [PasswordResetController::class, 'reset']);
        Route::post('social', [AuthController::class, 'social']);
        Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify');
        Route::post('email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:5,1')->name('verification.resend');
    });

    // Payment Webhooks (Public)
    Route::prefix('payment/webhook')->group(function () {
        Route::post('stripe', [WebhookController::class, 'handleStripe']);
        // Paystack webhook ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â register this URL in Paystack dashboard: https://api.onwynd.com/api/v1/payment/webhook/paystack
        Route::post('paystack', [WebhookController::class, 'handlePaystack']);
        Route::post('flutterwave', [PaymentControllerV1::class, 'handleWebhook'])->name('payment.webhook.flutterwave');
        // DodoPayments webhook ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â register in DodoPayments dashboard
        Route::post('dodo', [DodoPaymentsController::class, 'webhook'])->name('payment.webhook.dodo');
    });

    // Payment Callback (Public)
    Route::get('payments/callback', [PaymentControllerV1::class, 'verifyCallback'])->name('payment.callback');

    // Daily Tips Routes (Public)
    Route::prefix('daily-tips')->group(function () {
        Route::get('today', [\App\Http\Controllers\API\V1\DailyTipController::class, 'getTodayTip']);
        Route::get('category/{category}', [\App\Http\Controllers\API\V1\DailyTipController::class, 'getTipsByCategory']);

        // Admin-only routes for tip management
        Route::middleware(['auth:sanctum', 'verified'])->group(function () {
            Route::post('generate', [\App\Http\Controllers\API\V1\DailyTipController::class, 'generateTip'])->middleware('can:admin');
            Route::post('regenerate-today', [\App\Http\Controllers\API\V1\DailyTipController::class, 'regenerateTodayTip'])->middleware('can:admin');
        });
    });

    // Knowledge Base Routes (Public + Protected)
    Route::prefix('knowledge-base')->group(function () {
        // Public routes (Controller handles visibility based on auth)
        Route::get('faq', [KnowledgeBaseController::class, 'faq']);
        Route::get('topics', [KnowledgeBaseController::class, 'topics']);
        Route::get('categories', [KnowledgeBaseController::class, 'categories']);
        Route::get('articles', [KnowledgeBaseController::class, 'index']);
        Route::get('articles/{slug}', [KnowledgeBaseController::class, 'show']);

        // Protected routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('corporate', [KnowledgeBaseController::class, 'corporate']);

            Route::post('articles', [KnowledgeBaseController::class, 'store']);
            Route::put('articles/{id}', [KnowledgeBaseController::class, 'update']);
            Route::delete('articles/{id}', [KnowledgeBaseController::class, 'destroy']);
            Route::post('articles/{id}/feedback', [KnowledgeBaseController::class, 'feedback']);
        });
    });

    // Core auth endpoints ? only need a valid token, NOT email verification.
    // These must remain accessible so an unverified user can still log out,
    // refresh their token, or load their profile to show a "verify your email" notice.
    // Onboarding is also here ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â users should complete profile setup before/while verifying email.
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/device-token', [DeviceTokenController::class, 'store'])->name('auth.device_token.store');
        Route::delete('auth/device-token', [DeviceTokenController::class, 'destroy'])->name('auth.device_token.destroy');
        Route::post('auth/exchange-token', [AuthController::class, 'exchangeToken']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::get('auth/me', [AuthController::class, 'user']);

        // Onboarding ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â accessible pre-verification so new users can complete profile setup
        Route::get('onboarding', [OnboardingController::class, 'show']);
        Route::post('onboarding', [OnboardingController::class, 'store']);
        Route::post('onboarding/complete', [OnboardingController::class, 'store']);

        // Therapist online-status heartbeat (TODO-9 stub ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â full impl in TODOS.md)
        // Called every 90s by therapist dashboard. Marks the user online + stamps last_seen_at.
        // Scheduled command users:mark-offline runs every 5 min and sets is_online=false
        // for any user whose last_seen_at is older than 30 minutes.
        Route::post('me/heartbeat', function (\Illuminate\Http\Request $request) {
            $request->user()->update([
                'is_online'    => true,
                'last_seen_at' => now(),
            ]);
            return response()->noContent();
        })->middleware('throttle:40,1');
    });

    // Authenticated routes ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â valid token required.
    // Email verification is encouraged (banner shown on frontend) but NOT enforced here
    // so patients can access sessions, dashboard, and assessments immediately after signup.
    Route::middleware(['auth:sanctum'])->group(function () {

        // Sales Architecture Routes
        Route::get('leads', [\App\Http\Controllers\API\V1\Sales\LeadController::class, 'index']);
        Route::patch('leads/{id}/handoff', [\App\Http\Controllers\API\V1\Sales\LeadController::class, 'handoff']);
        Route::post('leads/{id}/assign-me', [\App\Http\Controllers\API\V1\Sales\LeadController::class, 'assignMe']);
        Route::get('institutional/health-overview', [\App\Http\Controllers\API\V1\Institutional\HealthController::class, 'index']);
        Route::get('sales/stats', [\App\Http\Controllers\API\V1\Sales\DashboardController::class, 'stats']); // Ensure accessible
        Route::get('sales/agent-performance', [\App\Http\Controllers\API\V1\Sales\DashboardController::class, 'agentPerformance']);

        // Agent location update (sales agents)
        Route::post('map/agent/location', [\App\Http\Controllers\API\V1\MapController::class, 'updateAgentLocation']);

        // Admin-only AI companion debug endpoint
        Route::prefix('admin')->middleware(['role:admin|clinical_manager|ceo|clinical_advisor'])->group(function () {
            Route::post('ai/companion/escalation-test', [CompanionDebugController::class, 'trigger']);
        });

        // Marketing settings (admin-only recommended via policies/middleware)
        Route::prefix('marketing')->group(function () {
            Route::post('ambassador-settings', [AmbassadorSettingsController::class, 'upsert']);
            Route::put('ambassador-settings', [AmbassadorSettingsController::class, 'upsert']);
        });

        // Generic user account routes (all roles)
        Route::prefix('user')->group(function () {
            Route::get('profile', [\App\Http\Controllers\API\V1\Account\UserAccountController::class, 'getProfile']);
            Route::put('profile', [\App\Http\Controllers\API\V1\Account\UserAccountController::class, 'updateProfile']);
            Route::post('password/change', [\App\Http\Controllers\API\V1\Account\UserAccountController::class, 'changePassword']);
            Route::post('password/set', [\App\Http\Controllers\API\V1\Account\UserAccountController::class, 'setInitialPassword']);
            Route::get('settings', [PatientSettingsController::class, 'index']);
            Route::put('settings', [PatientSettingsController::class, 'update']);
            Route::get('dashboard', [PatientDashboardController::class, 'index']);
            Route::get('mood-logs', [PatientMoodController::class, 'index']);
            Route::post('mood-logs', [PatientMoodController::class, 'store']);
            Route::get('devices', [PatientProfileController::class, 'devices']);
            Route::delete('devices/{id}', [PatientProfileController::class, 'unlinkDevice']);
        });

        // Account management routes (all roles)
        Route::prefix('account')->group(function () {
            Route::get('profile', [\App\Http\Controllers\API\V1\Account\UserAccountController::class, 'getProfile']);
            Route::put('profile', [\App\Http\Controllers\API\V1\Account\UserAccountController::class, 'updateProfile']);
            Route::post('change-password', [\App\Http\Controllers\API\V1\Account\UserAccountController::class, 'changePassword']);
            Route::post('change-email', [\App\Http\Controllers\API\V1\Account\UserAccountController::class, 'updateEmail']);
            Route::post('two-factor/setup', [\App\Http\Controllers\API\V1\Account\UserAccountController::class, 'setupTwoFactor']);
            Route::post('two-factor/enable', [\App\Http\Controllers\API\V1\Account\UserAccountController::class, 'enableTwoFactor']);
            Route::post('two-factor/disable', [\App\Http\Controllers\API\V1\Account\UserAccountController::class, 'disableTwoFactor']);
            Route::get('notification-settings', [\App\Http\Controllers\API\V1\Account\UserAccountController::class, 'getNotificationSettings']);
            Route::put('notification-settings', [\App\Http\Controllers\API\V1\Account\UserAccountController::class, 'updateNotificationSettings']);
            Route::get('history', [\App\Http\Controllers\API\V1\Account\UserAccountController::class, 'getAccountHistory']);
            Route::post('delete', [\App\Http\Controllers\API\V1\Account\UserAccountController::class, 'requestAccountDeletion']);
            Route::post('cancel-deletion', [\App\Http\Controllers\API\V1\Account\UserAccountController::class, 'cancelAccountDeletion']);
            Route::get('referral-code', [\App\Http\Controllers\API\V1\Account\ReferralController::class, 'getCode']);
            Route::get('referrals', [\App\Http\Controllers\API\V1\Account\ReferralController::class, 'history']);
        });

        // Shared subscription routes (accessible to all authenticated users)
        Route::prefix('subscriptions')->group(function () {
            Route::get('plans', [PatientSubscriptionController::class, 'plans']);
            Route::get('plans/{uuid}', [SubscriptionPlanController::class, 'show']);
            Route::get('current', [PatientSubscriptionController::class, 'current']);
            Route::post('subscribe', [PatientSubscriptionController::class, 'subscribe']);
            Route::post('cancel', [PatientSubscriptionController::class, 'cancel']);
            Route::post('resume', [PaymentSubscriptionController::class, 'resume']);
            Route::post('change-plan', [PaymentSubscriptionController::class, 'changePlan']);
            Route::get('history', [PatientSubscriptionController::class, 'payments']);
        });

        // Payment method management routes
        Route::prefix('payment-methods')->group(function () {
            Route::get('/', [PaymentControllerV1::class, 'getPaymentMethods']);
            Route::post('/', [PaymentControllerV1::class, 'addPaymentMethod']);
            Route::delete('{uuid}', [PaymentControllerV1::class, 'deletePaymentMethod']);
            Route::post('{uuid}/set-default', [PaymentControllerV1::class, 'setDefaultPaymentMethod']);
        });

        // Authenticated payment initialization (throttled 20/min ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â requires valid session)
        Route::middleware('throttle:20,1')->group(function () {
            Route::post('payments/subscription/initialize', [PaymentControllerV1::class, 'initializeSubscriptionPayment'])->name('payment.subscription.initialize');
            Route::post('payments/session/initialize', [PaymentControllerV1::class, 'initializeSessionPayment'])->name('payment.session.initialize');
            Route::post('payments/one-time/initialize', [PaymentControllerV1::class, 'initializeOneTimePayment'])->name('payment.one_time.initialize');
            Route::post('payments/group-session/initialize', [PaymentControllerV1::class, 'initializeGroupSessionPayment'])->name('payment.group_session.initialize');
            // DodoPayments (international / USD)
            Route::post('payments/dodo/initialize', [DodoPaymentsController::class, 'initialize'])->name('payment.dodo.initialize');
            Route::get('payments/dodo/verify/{reference}', [DodoPaymentsController::class, 'verify'])->name('payment.dodo.verify');
            // Flutterwave (NGN alternative)
            Route::post('payments/flutterwave/initialize', [FlutterwaveController::class, 'initialize'])->name('payment.flutterwave.initialize');
            Route::get('payments/flutterwave/verify/{tx_ref}', [FlutterwaveController::class, 'verify'])->name('payment.flutterwave.verify');
            // Klump BNPL (NGN buy-now-pay-later)
            Route::post('payments/klump/initialize', [KlumpController::class, 'initialize'])->name('payment.klump.initialize');
        });

        // Additional payment aliases
        Route::get('payments/history', [PaymentControllerV1::class, 'getPaymentHistory'])->name('payment.history.alias');
        Route::get('payments/verify/{reference}', [PaymentControllerV1::class, 'verifyByReference'])->name('payment.verify.get');
        Route::post('payments/retry/{reference}', [PaymentControllerV1::class, 'retryPayment'])->name('payment.retry');

        Route::prefix('onboarding')->group(function () {
            Route::get('/', [OnboardingController::class, 'show']);
            Route::post('/', [OnboardingController::class, 'store']);
            Route::post('complete', [OnboardingController::class, 'store']);
        });

        // Common Resources
        Route::get('subscription-plans', [SubscriptionPlanController::class, 'index']);

        // Gamification aliases ? frontend calls these without the /patient prefix
        Route::get('gamification', [GamificationController::class, 'index']);
        Route::get('user/badges', [GamificationController::class, 'badges']);
        Route::get('user/streak', [GamificationController::class, 'streak']);
        Route::get('leaderboards', [GamificationController::class, 'leaderboards']);
        Route::get('challenge/current', [GamificationController::class, 'currentChallenge']);

        // Platform branding ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â readable by all authenticated users
        Route::get('platform/branding', [AdminSettingsController::class, 'getPlatformBranding']);
        Route::get('admin/platform/branding', [AdminSettingsController::class, 'getPlatformBranding']);

        // Budget settings ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â readable by all authenticated users (used by intelligent creation switch)
        // Returns group=budgets settings, e.g. { sales_can_create: true }
        Route::get('settings/budgets', function () {
            $rows = \App\Models\Setting::where('group', 'budgets')->pluck('value', 'key');
            $data = $rows->map(function ($value) {
                // Coerce boolean-string values
                if (in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true)) return true;
                if (in_array(strtolower((string) $value), ['0', 'false', 'no', 'off'], true)) return false;
                return $value;
            })->toArray();
            return response()->json(['data' => $data]);
        });

        // Shared in-app notifications ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â accessible to all authenticated users regardless of role.
        // The controller scopes all queries to auth()->id() so no cross-user data leakage is possible.
        Route::prefix('notifications')->group(function () {
            // Push subscription management
            Route::post('subscribe', [\App\Http\Controllers\API\V1\PushNotificationController::class, 'subscribe']);
            Route::post('unsubscribe', [\App\Http\Controllers\API\V1\PushNotificationController::class, 'unsubscribe']);
            // In-app notification reads (used by NotificationBell for all staff roles)
            Route::get('/', [\App\Http\Controllers\API\V1\Patient\NotificationController::class, 'index']);
            Route::get('unread-count', [\App\Http\Controllers\API\V1\Patient\NotificationController::class, 'unreadCount']);
            Route::patch('read-all', [\App\Http\Controllers\API\V1\Patient\NotificationController::class, 'markAsRead']);
            Route::patch('{id}/read', [\App\Http\Controllers\API\V1\Patient\NotificationController::class, 'markAsRead']);
            Route::delete('{id}', [\App\Http\Controllers\API\V1\Patient\NotificationController::class, 'destroy']);
            // Notification preferences ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â shared across all roles
            Route::get('preferences', [\App\Http\Controllers\API\V1\NotificationPreferencesController::class, 'show']);
            Route::put('preferences', [\App\Http\Controllers\API\V1\NotificationPreferencesController::class, 'update']);
        });

        // AI Routes
        Route::prefix('ai/diagnostic')->group(function () {
            Route::post('start', [\App\Http\Controllers\API\V1\AI\AIDiagnosticController::class, 'start']);
            Route::post('{sessionId}/message', [\App\Http\Controllers\API\V1\AI\AIDiagnosticController::class, 'message']);
            Route::get('{sessionId}', [\App\Http\Controllers\API\V1\AI\AIDiagnosticController::class, 'show']);
        });
        Route::post('ai/transcribe', [\App\Http\Controllers\API\V1\AI\TranscriptionController::class, 'store']);

        // Marketing newsletter admin routes (protected)
        Route::prefix('marketing/newsletter')->group(function () {
            Route::get('subscribers', [MarketingNewsletterController::class, 'index']);
            Route::post('unsubscribe', [MarketingNewsletterController::class, 'unsubscribeEmail']);
        });

        // Marketing events and broadcast (protected)
        Route::prefix('marketing/events')->group(function () {
            Route::get('/', [MarketingEventController::class, 'index']);
            Route::post('/', [MarketingEventController::class, 'store']);
            Route::put('{id}', [MarketingEventController::class, 'update']);
            Route::delete('{id}', [MarketingEventController::class, 'destroy']);
        });
        Route::post('marketing/broadcast/send', [MarketingBroadcastController::class, 'send']);
        Route::post('marketing/broadcast/preview', [MarketingBroadcastController::class, 'preview']);

        // AI Companion Routes
        Route::prefix('ai')->group(function () {
            Route::get('quota', [\App\Http\Controllers\API\V1\AI\AICompanionController::class, 'quotaStatus']);
            Route::post('chat', [\App\Http\Controllers\API\V1\AI\AICompanionController::class, 'chat'])->middleware(['ai.quota', 'throttle:ai-chat']);
            Route::post('chat/stream', [\App\Http\Controllers\API\V1\AI\AICompanionController::class, 'stream'])->middleware(['ai.quota', 'throttle:ai-chat']);
            // History aliases to match client expectations
            Route::get('history/{conversationId}', [\App\Http\Controllers\API\V1\AI\AICompanionController::class, 'getConversation']);
            Route::delete('history/{conversationId}', [\App\Http\Controllers\API\V1\AI\AICompanionController::class, 'deleteConversation']);
            // Existing conversation routes remain
            Route::get('conversations', [\App\Http\Controllers\API\V1\AI\AICompanionController::class, 'getConversations']);
            Route::get('conversations/{conversationId}', [\App\Http\Controllers\API\V1\AI\AICompanionController::class, 'getConversation']);
            Route::delete('conversations/{conversationId}', [\App\Http\Controllers\API\V1\AI\AICompanionController::class, 'deleteConversation']);
            // Voice transcription for chat voice notes
            Route::post('transcribe', [\App\Http\Controllers\API\V1\AI\TranscriptionController::class, 'store']);
            // Document/image analysis ? extracts text from PDF/DOCX/TXT or describes images via vision API
            Route::post('analyze-document', [DocumentAnalysisController::class, 'extract']);
            // Companion personal notes (hobbies, food, activities learned from chat)
            Route::patch('companion/notes', [\App\Http\Controllers\API\V1\AI\AICompanionController::class, 'updateCompanionNotes']);
            Route::get('companion/notes', [\App\Http\Controllers\API\V1\AI\AICompanionController::class, 'getCompanionNotes']);
            // Message feedback (thumbs up / down)
            Route::post('chats/{id}/feedback', [\App\Http\Controllers\API\V1\AI\AICompanionController::class, 'feedback']);
        });

        // Recent assessment results + delete (used by frontend assessment UI)
        Route::get('assessments/results/recent', [\App\Http\Controllers\API\V1\Assessment\AssessmentResultController::class, 'recent']);
        Route::delete('assessments/results/{id}', [\App\Http\Controllers\API\V1\Assessment\AssessmentResultController::class, 'destroy']);

        // Guest assessment routes (public - no authentication required)
        Route::prefix('assessments/guest')->group(function () {
            Route::post('submit', [\App\Http\Controllers\API\V1\Assessment\GuestAssessmentController::class, 'submit']);
            Route::get('{guest_token}', [\App\Http\Controllers\API\V1\Assessment\GuestAssessmentController::class, 'show']);
        });

        // Therapy Audio Session Routes
        Route::post('therapy/audio/start', [\App\Http\Controllers\API\V1\Therapy\AudioSessionController::class, 'start']);
        Route::post('therapy/audio/end', [\App\Http\Controllers\API\V1\Therapy\AudioSessionController::class, 'end']);
        Route::post('therapy/audio/metrics', [\App\Http\Controllers\API\V1\Therapy\AudioSessionController::class, 'recordMetrics']);

        // Therapy Video (LiveKit)
        Route::prefix('therapy/video')->group(function () {
            Route::post('token', [\App\Http\Controllers\API\V1\Therapy\LiveKitController::class, 'token'])->middleware('throttle:10,1');
            Route::post('consent', [\App\Http\Controllers\API\V1\Therapy\LiveKitController::class, 'consent']);
        });
        // Video aliases for client compatibility
        Route::prefix('video')->group(function () {
            Route::post('token', [\App\Http\Controllers\API\V1\Therapy\LiveKitController::class, 'token'])->middleware('throttle:10,1');
            Route::post('rooms', [\App\Http\Controllers\API\V1\Therapy\LiveKitController::class, 'createRoom']);
            Route::get('rooms/{appointmentId}', [\App\Http\Controllers\API\V1\Therapy\LiveKitController::class, 'getRoomByAppointment']);
            Route::delete('rooms/{roomName}', [\App\Http\Controllers\API\V1\Therapy\LiveKitController::class, 'endRoom']);
            Route::get('rooms/{roomName}/participants', [\App\Http\Controllers\API\V1\Therapy\LiveKitController::class, 'participantsByRoom']);
        });

        // Sessions Booking & LiveKit Join (frontend-aligned)
        Route::get('sessions/active', [\App\Http\Controllers\API\V1\Session\SessionController::class, 'getActiveSession']);
        Route::get('sessions/booking-fee-preview', [\App\Http\Controllers\API\V1\Session\SessionController::class, 'feePreview']);
        Route::post('sessions/book', [\App\Http\Controllers\API\V1\Session\SessionController::class, 'bookSession'])->middleware('throttle:booking');
        Route::post('sessions/{uuid}/participant-joined', [\App\Http\Controllers\API\V1\Session\SessionController::class, 'participantJoined']);
        Route::post('sessions/{uuid}/review', [\App\Http\Controllers\API\V1\Session\SessionReviewController::class, 'store']);

        // Booking Intent (abandoned-booking recovery tracking)
        Route::post('booking-intents', [\App\Http\Controllers\API\V1\BookingIntentController::class, 'store']);
        Route::post('booking-intents/complete', [\App\Http\Controllers\API\V1\BookingIntentController::class, 'complete']);

        // Group Sessions
        Route::get('group-sessions', [\App\Http\Controllers\API\V1\Session\GroupSessionController::class, 'index']);
        Route::post('group-sessions', [\App\Http\Controllers\API\V1\Session\GroupSessionController::class, 'store']);
        Route::get('group-sessions/{uuid}', [\App\Http\Controllers\API\V1\Session\GroupSessionController::class, 'show']);
    });

    // Public/Guest Group Session Access (Optional Auth handled in Controller)
    Route::post('group-sessions/{uuid}/join', [\App\Http\Controllers\API\V1\Session\GroupSessionController::class, 'join']);
    Route::get('group-sessions/{uuid}/video/join', [\App\Http\Controllers\API\V1\Therapy\LiveKitController::class, 'joinGroup']);
    Route::get('group-sessions/{uuid}/summary', [\App\Http\Controllers\API\V1\Session\GroupSessionController::class, 'summary']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('group-sessions/{uuid}/end', [\App\Http\Controllers\API\V1\Session\GroupSessionController::class, 'end']);
        Route::post('group-sessions/{uuid}/invite', [\App\Http\Controllers\API\V1\Session\GroupSessionController::class, 'invite']);

        // Quota management endpoints
        Route::prefix('quota')->group(function () {
            Route::get('status', [QuotaController::class, 'getQuotaStatus']);
            Route::get('usage-history', [QuotaController::class, 'getUsageHistory']);
            Route::post('can-book', [QuotaController::class, 'canBookSession']);
        });

        Route::prefix('sessions/{uuid}/video')->group(function () {
            Route::post('join', [\App\Http\Controllers\API\V1\Therapy\LiveKitController::class, 'join'])->middleware('throttle:10,1');
            Route::post('end', [\App\Http\Controllers\API\V1\Therapy\LiveKitController::class, 'end']);
            Route::post('leave', [\App\Http\Controllers\API\V1\Therapy\LiveKitController::class, 'leave']);
            Route::get('status', [\App\Http\Controllers\API\V1\Therapy\LiveKitController::class, 'roomStatus']);
            Route::get('participants', [\App\Http\Controllers\API\V1\Therapy\LiveKitController::class, 'participants']);
        });

        // Chat routes
        Route::prefix('chat')->group(base_path('routes/api/v1/chat.php'));

        // Call Routes (WebRTC Signaling)
        Route::post('calls/initiate', [\App\Http\Controllers\API\V1\Chat\CallController::class, 'initiate']);
        Route::post('calls/{callId}/signal', [\App\Http\Controllers\API\V1\Chat\CallController::class, 'signal']);

        // Promotional code validation (all authenticated users)
        Route::post('promo-codes/validate', [PatientPromotionalCodeController::class, 'validateCode']);

        // Patient routes
        Route::prefix('patient')->middleware(['role:patient|user|customer|investor'])->group(function () {
            Route::get('dashboard', [PatientDashboardController::class, 'index']);
            // Session sub-routes BEFORE apiResource so named routes don't conflict
            Route::get('sessions/upcoming', [PatientSessionController::class, 'upcoming']);
            Route::get('sessions/past', [PatientSessionController::class, 'past']);
            Route::get('sessions/history', [PatientSessionController::class, 'history']);
            Route::post('sessions/{uuid}/cancel', [PatientSessionController::class, 'cancel']);
            Route::put('sessions/{uuid}/reschedule', [PatientSessionController::class, 'reschedule']);
            Route::post('sessions/{uuid}/join', [PatientSessionController::class, 'joinMeeting']);
            Route::post('sessions/{uuid}/end', [PatientSessionController::class, 'end']);
            Route::post('sessions/{uuid}/rate', [PatientSessionController::class, 'rate']);
            Route::get('sessions/{uuid}/summary', [PatientSessionController::class, 'summary']);
            Route::apiResource('sessions', PatientSessionController::class, ['as' => 'patient']);
            Route::get('assessments/questions', [PatientAssessmentController::class, 'questions']);
            Route::get('assessments/categories', [PatientAssessmentController::class, 'categories']);
            Route::post('assessments/submit', [PatientAssessmentController::class, 'store']);
            Route::post('assessments/{uuid}/submit', [PatientAssessmentController::class, 'submitByUuid']);
            Route::post('assessments/retake', [PatientAssessmentController::class, 'retake']);
            Route::get('assessments/history', [PatientAssessmentController::class, 'history']);
            Route::apiResource('assessments', PatientAssessmentController::class);
            // Guest assessment linking (requires authentication)
            Route::post('assessments/guest/link', [\App\Http\Controllers\API\V1\Assessment\GuestAssessmentController::class, 'link']);
            // Chat routes
            Route::apiResource('chats', PatientChatController::class);
            Route::delete('chat/conversations/{id}', [PatientChatController::class, 'destroy']);
            Route::get('chat/conversations/{id}/export', [PatientChatController::class, 'export']);
            Route::get('chat/stats', [PatientChatController::class, 'stats']);

            Route::get('therapists', [PatientTherapistController::class, 'index']);
            Route::get('therapists/{id}', [PatientTherapistController::class, 'show']);
            // people endpoint ? alias for the patient's therapist list, used by the dashboard
            Route::get('people', [PatientTherapistController::class, 'index']);
            Route::apiResource('payments', PatientPaymentController::class);

            // Notifications
            Route::get('notifications/unread-count', [PatientNotificationController::class, 'unreadCount']);
            Route::get('notifications', [PatientNotificationController::class, 'index']);
            Route::patch('notifications/read-all', [PatientNotificationController::class, 'markAsRead']);
            Route::patch('notifications/{id}/read', [PatientNotificationController::class, 'markAsRead']);
            Route::delete('notifications/delete-all', [PatientNotificationController::class, 'deleteAll']);
            Route::delete('notifications/{id}', [PatientNotificationController::class, 'destroy']);

            // Profile Routes
            Route::get('profile', [PatientProfileController::class, 'show']);
            Route::put('profile', [PatientProfileController::class, 'update']);
            Route::delete('profile', [PatientProfileController::class, 'deleteAccount']);
            Route::post('profile/setup', [PatientProfileController::class, 'setup']);
            Route::post('profile/photo', [PatientProfileController::class, 'uploadPhoto']);
            Route::get('profile/settings', [PatientProfileController::class, 'getSettings']);
            Route::put('profile/settings', [PatientProfileController::class, 'updateSettings']);
            Route::put('profile/notifications', [PatientProfileController::class, 'updateNotifications']);
            Route::post('profile/biometric', [PatientProfileController::class, 'biometric']);
            Route::post('profile/change-password', [PatientProfileController::class, 'changePassword']);
            Route::get('profile/devices', [PatientProfileController::class, 'devices']);
            Route::delete('profile/devices/{id}', [PatientProfileController::class, 'unlinkDevice']);
            Route::delete('profile/photo', [PatientProfileController::class, 'deletePhoto']);
            Route::get('profile/preferences', [PatientProfileController::class, 'getPreferences']);
            Route::put('profile/preferences', [PatientProfileController::class, 'updatePreferences']);

            // Route::post('reviews', [ReviewController::class, 'store']);

            // Habit Tracking
            Route::get('habits/stats', [PatientHabitController::class, 'stats']);
            Route::apiResource('habits', PatientHabitController::class)->middleware([
                'store' => 'activity.quota',
            ]);
            Route::post('habits/{habit}/toggle', [PatientHabitController::class, 'toggle']);
            Route::get('habits/{habit}/logs', [PatientHabitController::class, 'logs']);
            Route::delete('habits/{habit}/logs/{logId}', [PatientHabitController::class, 'deleteLog']);
            Route::get('habits/{habit}/calendar', [PatientHabitController::class, 'calendar']);
            Route::post('habits/{habit}/log', [PatientHabitController::class, 'log'])->middleware('activity.quota');

            // Favorites
            Route::get('favorites', [PatientFavoriteController::class, 'index']);
            Route::post('favorites', [PatientFavoriteController::class, 'store']);
            Route::delete('favorites/{id}', [PatientFavoriteController::class, 'destroy']);

            // Mood Tracking
            Route::get('moods/ai-suggestions', [PatientMoodController::class, 'aiSuggestions']);
            Route::get('moods', [PatientMoodController::class, 'index']);
            Route::post('moods', [PatientMoodController::class, 'store'])->middleware('activity.quota');
            Route::get('moods/stats', [PatientMoodController::class, 'stats']);

            // Sleep Tracking
            Route::get('sleep/stats', [PatientSleepController::class, 'stats']);
            Route::get('sleep/trends', [PatientSleepController::class, 'trends']);
            Route::get('sleep/insights', [PatientSleepController::class, 'insights']);
            Route::apiResource('sleep/logs', PatientSleepController::class)->middleware([
                'store' => 'activity.quota',
            ]);
            Route::post('sleep/schedule', [PatientSleepController::class, 'updateSchedule']);

            // Mindfulness Tracking
            Route::apiResource('mindfulness', PatientMindfulnessController::class);
            // Legacy /mindful/ paths (kept for backwards-compat)
            Route::get('mindful/exercises', [PatientMindfulnessController::class, 'exercises']);
            Route::get('mindful/exercises/{id}', [PatientMindfulnessController::class, 'showExercise']);
            Route::post('mindful/sessions/start', [PatientMindfulnessController::class, 'startSession'])->middleware('activity.quota');
            Route::get('mindful/soundscapes', [PatientMindfulnessController::class, 'soundscapes']);
            Route::get('mindful/soundscapes/search', [PatientMindfulnessController::class, 'searchSoundscapes']);
            // /mindfulness/ prefixed paths used by the frontend service
            Route::get('mindfulness/exercises/recommended', [PatientMindfulnessController::class, 'recommendedExercises']);
            Route::get('mindfulness/exercises/{id}', [PatientMindfulnessController::class, 'showExercise']);
            Route::get('mindfulness/exercises', [PatientMindfulnessController::class, 'exercises']);
            Route::get('mindfulness/sessions/active', [PatientMindfulnessController::class, 'activeSession']);
            Route::post('mindfulness/sessions/start', [PatientMindfulnessController::class, 'startSession'])->middleware('activity.quota');
            Route::post('mindfulness/sessions/{id}/complete', [PatientMindfulnessController::class, 'completeSession']);
            Route::post('mindfulness/sessions/{id}/cancel', [PatientMindfulnessController::class, 'cancelSession']);
            Route::get('mindfulness/sessions/{id}', [PatientMindfulnessController::class, 'getSession']);
            Route::delete('mindfulness/sessions/{id}', [PatientMindfulnessController::class, 'deleteSession']);
            Route::get('mindfulness/sessions', [PatientMindfulnessController::class, 'getSessions']);
            Route::get('mindfulness/stats', [PatientMindfulnessController::class, 'getStats']);
            Route::get('mindfulness/trends', [PatientMindfulnessController::class, 'getTrends']);

            // Journal Tracking
            Route::get('journal/stats', [PatientJournalController::class, 'stats']);
            Route::get('journal/types', [PatientJournalController::class, 'types']);
            Route::get('journal/search', [PatientJournalController::class, 'search']);
            Route::get('journal/tags', [PatientJournalController::class, 'tags']);
            Route::get('journal/export', [PatientJournalController::class, 'export']);
            Route::apiResource('journal', PatientJournalController::class)->middleware([
                'store' => 'activity.quota',
            ]);

            // Stress Management
            Route::get('stress/overview', [PatientStressController::class, 'overview']);
            Route::get('stress/trends', [PatientStressController::class, 'trends']);
            Route::post('stress/coping-strategies', [PatientStressController::class, 'copingStrategies']);
            Route::get('stress/common-symptoms', [PatientStressController::class, 'commonSymptoms']);
            Route::apiResource('stress', PatientStressController::class);

            // Mindful Resources (Library)
            Route::get('resources/categories', [PatientResourceController::class, 'categories']);
            Route::post('resources/{id}/complete', [PatientResourceController::class, 'complete']);
            Route::apiResource('resources', PatientResourceController::class, ['as' => 'patient'])->only(['index', 'show']);

            // Search & Filters
            Route::get('search', [PatientSearchController::class, 'index']);
            Route::get('search/suggestions', [PatientSearchController::class, 'suggestions']);
            Route::get('search/recent', [PatientSearchController::class, 'recent']);
            Route::delete('search/history', [PatientSearchController::class, 'clearHistory']);

            // Crisis Support
            Route::get('crisis/resources', [PatientCrisisController::class, 'index']);
            Route::post('crisis/alert', [PatientCrisisController::class, 'alert']);
            Route::get('crisis/safety-plan', [PatientCrisisController::class, 'getSafetyPlan']);
            Route::put('crisis/safety-plan', [PatientCrisisController::class, 'updateSafetyPlan']);
            Route::post('crisis/call-emergency', [PatientCrisisController::class, 'callEmergency']);

            // Settings
            Route::get('settings', [PatientSettingsController::class, 'index']);
            Route::put('settings', [PatientSettingsController::class, 'update']);

            // Help
            Route::get('help/faqs', [PatientHelpController::class, 'faqs']);
            Route::get('help/search', [PatientHelpController::class, 'search']);
            Route::post('help/contact', [PatientHelpController::class, 'contact']);
            Route::post('help/feedback', [PatientHelpController::class, 'feedback']);
            Route::post('help/bug-report', [PatientHelpController::class, 'reportBug']);
            Route::get('app/version', [PatientHelpController::class, 'version']);

            // Referral System
            Route::prefix('referral')->group(function () {
                Route::get('code', [\App\Http\Controllers\API\V1\Patient\ReferralController::class, 'getReferralCode']);
                Route::post('code/generate', [\App\Http\Controllers\API\V1\Patient\ReferralController::class, 'generateReferralCode']);
                Route::get('list', [\App\Http\Controllers\API\V1\Patient\ReferralController::class, 'getReferrals']);
                Route::get('stats', [\App\Http\Controllers\API\V1\Patient\ReferralController::class, 'getStats']);
                Route::get('rewards', [\App\Http\Controllers\API\V1\Patient\ReferralController::class, 'getRewards']);
                Route::post('rewards/{rewardId}/redeem', [\App\Http\Controllers\API\V1\Patient\ReferralController::class, 'redeemReward']);
                Route::get('leaderboard', [\App\Http\Controllers\API\V1\Patient\ReferralController::class, 'getLeaderboard']);
                Route::post('share/email', [\App\Http\Controllers\API\V1\Patient\ReferralController::class, 'shareViaEmail']);
                Route::post('track-click', [\App\Http\Controllers\API\V1\Patient\ReferralController::class, 'trackClick']);
            });

            // Ambassador System
            Route::prefix('ambassador')->group(function () {
                Route::get('profile', [\App\Http\Controllers\API\V1\Patient\AmbassadorController::class, 'profile']);
                Route::post('apply', [\App\Http\Controllers\API\V1\Patient\AmbassadorController::class, 'apply']);
                Route::get('leaderboard', [\App\Http\Controllers\API\V1\Patient\AmbassadorController::class, 'leaderboard']);
                Route::post('generate-code', [\App\Http\Controllers\API\V1\Patient\AmbassadorController::class, 'generateReferralCode']);
            });

            Route::prefix('onboarding')->group(function () {
                Route::get('first-login', [PatientOnboardingController::class, 'firstLoginStatus']);
                Route::post('first-login/complete', [PatientOnboardingController::class, 'firstLoginComplete']);
                Route::post('complete', [PatientOnboardingController::class, 'complete']);
                Route::post('breathing-complete', [PatientOnboardingController::class, 'breathingComplete']);
            });

            // Subscription & Payments
            Route::get('subscriptions/plans', [PatientSubscriptionController::class, 'plans']);
            Route::get('subscriptions/current', [PatientSubscriptionController::class, 'current']);
            Route::post('subscriptions/subscribe', [PatientSubscriptionController::class, 'subscribe']);
            Route::post('subscriptions/cancel', [PatientSubscriptionController::class, 'cancel']);
            Route::post('subscriptions/pause', [PatientSubscriptionController::class, 'pause']);
            Route::post('subscriptions/resume', [PatientSubscriptionController::class, 'resume']);
            Route::get('subscriptions/payments', [PatientSubscriptionController::class, 'payments']);
            Route::get('subscriptions/invoices', [PatientSubscriptionController::class, 'invoices']);

            // Game scores
            Route::prefix('game')->group(function () {
                Route::post('scores', [\App\Http\Controllers\API\V1\Game\GameScoreController::class, 'store']);
                Route::get('scores/me', [\App\Http\Controllers\API\V1\Game\GameScoreController::class, 'myStats']);
                Route::get('leaderboard', [\App\Http\Controllers\API\V1\Game\GameScoreController::class, 'leaderboard']);
            });

            // Gamification
            Route::get('gamification', [GamificationController::class, 'index']);
            Route::get('user/streak', [GamificationController::class, 'streak']);
            Route::post('user/streak/check-in', [GamificationController::class, 'checkIn']);
            Route::get('user/badges', [GamificationController::class, 'badges']);
            Route::post('user/badges/showcase', [GamificationController::class, 'showcaseBadge']);
            Route::get('leaderboards', [GamificationController::class, 'leaderboards']);
            Route::get('challenge/current', [GamificationController::class, 'currentChallenge']);
            Route::post('challenge/claim-reward', [GamificationController::class, 'claimReward']);

            // Wellness
            Route::prefix('wellness')->group(function () {
                Route::get('dashboard', [\App\Http\Controllers\API\V1\Patient\WellnessController::class, 'dashboard']);
                Route::get('score', [\App\Http\Controllers\API\V1\Patient\WellnessController::class, 'score']);
                Route::get('recommendations', [\App\Http\Controllers\API\V1\Patient\WellnessController::class, 'recommendations']);
                Route::post('check-in', [\App\Http\Controllers\API\V1\Patient\WellnessController::class, 'checkIn']);
                Route::get('check-in/history', [\App\Http\Controllers\API\V1\Patient\WellnessController::class, 'checkInHistory']);
                Route::get('insights', [\App\Http\Controllers\API\V1\Patient\WellnessController::class, 'insights']);
            Route::get('export', [\App\Http\Controllers\API\V1\Patient\WellnessController::class, 'export']);
            Route::post('export', [\App\Http\Controllers\API\V1\Patient\WellnessController::class, 'export']);
            });

            // Community
            Route::get('community/feed', [PatientCommunityController::class, 'index']);
            Route::post('community/posts', [PatientCommunityController::class, 'store']);
            Route::get('community/posts/{id}', [PatientCommunityController::class, 'show']);
            Route::post('community/posts/{id}/like', [PatientCommunityController::class, 'like']);
            Route::post('community/posts/{id}/comments', [PatientCommunityController::class, 'comment']);
            Route::delete('community/posts/{id}', [PatientCommunityController::class, 'destroy']);

            Route::get('courses', [PatientCourseCatalogController::class, 'index']);
            Route::get('courses/{uuid}', [PatientCourseCatalogController::class, 'show']);
            Route::post('courses/{uuid}/enroll', [PatientCourseCatalogController::class, 'enroll']);
            Route::get('communities', [PatientCommunityDirectoryController::class, 'index']);
            Route::get('communities/{uuid}', [PatientCommunityDirectoryController::class, 'show']);
            Route::post('communities/{uuid}/join', [PatientCommunityDirectoryController::class, 'join']);
            Route::post('communities/{uuid}/leave', [PatientCommunityDirectoryController::class, 'leave']);

            // Medical Records
            Route::get('medical/prescriptions', [PrescriptionController::class, 'index'])->middleware('feature:eprescriptions');
            Route::get('medical/prescriptions/{id}', [PrescriptionController::class, 'show'])->middleware('feature:eprescriptions');
            Route::apiResource('medical/medication-logs', MedicationLogController::class)->middleware('feature:medication_tracking');

            // Secure Documents
            Route::apiResource('documents', SecureDocumentController::class, ['as' => 'patient'])->middleware('feature:secure_documents');
        });

        // Therapist routes
        Route::prefix('therapist')->middleware(['role:therapist'])->group(function () {
            Route::get('dashboard', [TherapistDashboardController::class, 'index']);

            // Manual online/offline toggle ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â therapist explicitly sets their status.
            // Rules enforced here:
            //   ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ Can only go online if profile is verified (verification_status = approved)
            //   ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ Setting offline immediately clears is_online regardless of heartbeat
            // The heartbeat (POST /me/heartbeat) keeps is_online=true while dashboard is open.
            // This toggle takes precedence ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â going offline here suppresses heartbeat effect
            // until the therapist manually goes back online.
            Route::post('status/online', function (\Illuminate\Http\Request $request) {
                $user    = $request->user();
                $profile = $user->therapistProfile;

                if (!$profile || $profile->verification_status !== 'approved') {
                    return response()->json(['message' => 'Only approved therapists can go online.'], 403);
                }

                $user->update(['is_online' => true, 'last_seen_at' => now()]);
                return response()->json(['is_online' => true, 'message' => 'You are now online and visible to patients.']);
            });

            Route::post('status/offline', function (\Illuminate\Http\Request $request) {
                $request->user()->update(['is_online' => false]);
                return response()->json(['is_online' => false, 'message' => 'You are now offline and hidden from patients.']);
            });

            Route::apiResource('sessions', TherapistSessionController::class, ['as' => 'therapist']);
            Route::apiResource('availability', TherapistAvailabilityController::class);
            Route::apiResource('patients', TherapistPatientController::class, ['as' => 'therapist']);
            Route::post('patients/import', [TherapistPatientController::class, 'importPatients']);
            Route::get('patients/{id}/health-data/{type}', [TherapistPatientController::class, 'getHealthData']);
            Route::get('earnings', [TherapistEarningsController::class, 'index']);
            Route::post('earnings/payout', [TherapistEarningsController::class, 'requestPayout']);
            Route::get('earnings/payouts', [TherapistEarningsController::class, 'payouts']);
            Route::get('earnings-preview', [\App\Http\Controllers\API\V1\Therapist\EarningsController::class, 'preview']);
            Route::get('earnings/{month}', [\App\Http\Controllers\API\V1\Therapist\EarningsController::class, 'show']);
            Route::get('profile', [TherapistProfileController::class, 'show']);
            Route::put('profile', [TherapistProfileController::class, 'update']);
            Route::put('profile/rate', [TherapistProfileController::class, 'updateRate']);
            Route::put('bank-details', [TherapistProfileController::class, 'updateBankDetails']);
            Route::post('certificate', [TherapistProfileController::class, 'uploadCertificate']);
            Route::patch('terms/accept', [TherapistProfileController::class, 'acceptTerms']);

            Route::apiResource('notes', \App\Http\Controllers\API\V1\Therapist\TherapistNoteController::class);

            // Patient invites ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â therapist-initiated onboarding
            Route::post('patient-invites', [TherapistPatientInviteController::class, 'store']);
            Route::get('patient-invites', [TherapistPatientInviteController::class, 'index']);
            Route::delete('patient-invites/{patientInvite}', [TherapistPatientInviteController::class, 'destroy']);

            // Session confirmation endpoint
            Route::post('sessions/{id}/confirm', [TherapistSessionController::class, 'confirmSession']);
            Route::get('sessions/{uuid}/notes', [\App\Http\Controllers\API\V1\Therapist\TherapistNoteController::class, 'indexBySession']);

            // Stats / extra endpoints
            Route::get('stats', [\App\Http\Controllers\API\V1\Therapist\TherapistProfileController::class, 'stats']);
            Route::get('financial-flow', [\App\Http\Controllers\API\V1\Therapist\TherapistProfileController::class, 'financialFlow']);
            Route::get('notes', [\App\Http\Controllers\API\V1\Therapist\TherapistProfileController::class, 'notes']);
            Route::post('notes', [\App\Http\Controllers\API\V1\Therapist\TherapistProfileController::class, 'storeNote']);
            Route::put('notes/{id}', [\App\Http\Controllers\API\V1\Therapist\TherapistProfileController::class, 'updateNote']);
            Route::delete('notes/{id}', [\App\Http\Controllers\API\V1\Therapist\TherapistProfileController::class, 'deleteNote']);

            // Notifications (shared controller, queries by user_id so safe for any role)
            Route::get('notifications/unread-count', [PatientNotificationController::class, 'unreadCount']);
            Route::get('notifications', [PatientNotificationController::class, 'index']);
            Route::patch('notifications/read-all', [PatientNotificationController::class, 'markAsRead']);
            Route::patch('notifications/{id}/read', [PatientNotificationController::class, 'markAsRead']);
            Route::delete('notifications/delete-all', [PatientNotificationController::class, 'deleteAll']);
            Route::delete('notifications/{id}', [PatientNotificationController::class, 'destroy']);
            // Therapist ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ patient session notifications (fire-and-forget from frontend)
            Route::post('notifications/session-ready', [TherapistSessionController::class, 'notifySessionReady']);
            Route::post('notifications/patient-joined', [TherapistSessionController::class, 'notifyPatientJoined']);
            Route::post('notifications/session-starting', [TherapistSessionController::class, 'notifySessionStarting']);

            // Medical Management
            Route::apiResource('medical/prescriptions', PrescriptionController::class)->middleware('feature:eprescriptions');

            // Secure Documents
            Route::apiResource('documents', SecureDocumentController::class, ['as' => 'therapist'])->middleware('feature:secure_documents');

            // Stripe Connect (USD payouts for international therapists)
            Route::get('stripe/connect', [\App\Http\Controllers\API\V1\Therapist\StripeConnectController::class, 'connect']);
            Route::get('stripe/callback', [\App\Http\Controllers\API\V1\Therapist\StripeConnectController::class, 'callback']);
            Route::get('stripe/status', [\App\Http\Controllers\API\V1\Therapist\StripeConnectController::class, 'status']);
            Route::post('stripe/disconnect', [\App\Http\Controllers\API\V1\Therapist\StripeConnectController::class, 'disconnect']);
        });

        // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Staff Direct Messaging ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
        // Available to therapist, admin, super_admin, clinical_advisor, support,
        // coo, ceo, hr, compliance ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â any staff-to-staff conversation.
        // Uses the fully-built DirectChatController.
        Route::prefix('chat')->middleware(['role:therapist|admin|super_admin|clinical_advisor|support|coo|ceo|hr|compliance|finance|cfo|tech_team|product_manager'])->group(function () {
            Route::get('conversations',                [\App\Http\Controllers\API\V1\Chat\DirectChatController::class, 'getConversations']);
            Route::get('conversations/{userId}',       [\App\Http\Controllers\API\V1\Chat\DirectChatController::class, 'getConversation']);
            Route::post('messages',                    [\App\Http\Controllers\API\V1\Chat\DirectChatController::class, 'sendMessage']);
            Route::post('mark-as-read',                [\App\Http\Controllers\API\V1\Chat\DirectChatController::class, 'markAsRead']);
            Route::delete('messages/{id}',             [\App\Http\Controllers\API\V1\Chat\DirectChatController::class, 'deleteMessage']);
            Route::post('requests',                    [\App\Http\Controllers\API\V1\Chat\DirectChatController::class, 'sendChatRequest']);
            Route::get('requests/pending',             [\App\Http\Controllers\API\V1\Chat\DirectChatController::class, 'getPendingRequests']);
            Route::post('requests/{id}/accept',        [\App\Http\Controllers\API\V1\Chat\DirectChatController::class, 'acceptRequest']);
            Route::post('requests/{id}/reject',        [\App\Http\Controllers\API\V1\Chat\DirectChatController::class, 'rejectRequest']);
            Route::post('requests/{id}/block',         [\App\Http\Controllers\API\V1\Chat\DirectChatController::class, 'blockUser']);
            // Staff directory for starting new conversations
            Route::get('staff',  function (\Illuminate\Http\Request $request) {
                $me     = $request->user();
                $search = $request->get('search', '');
                $staff  = \App\Models\User::whereHas('role', fn ($q) => $q->whereNotIn('slug', ['patient', 'user']))
                    ->where('id', '!=', $me->id)
                    ->where('is_active', true)
                    ->when($search, fn ($q) =>
                        $q->where(fn ($r) =>
                            $r->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name',  'like', "%{$search}%")
                              ->orWhere('email',      'like', "%{$search}%")
                        )
                    )
                    ->with('role:id,name,slug')
                    ->select('id', 'uuid', 'first_name', 'last_name', 'email', 'profile_photo', 'is_online')
                    ->limit(30)
                    ->get();
                return response()->json(['success' => true, 'data' => $staff]);
            });
        });

        // Institutional routes ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â restricted to institutional roles and admin
        Route::prefix('institutional')->middleware(['auth:sanctum', 'role:institutional|institution_admin|university_admin|admin|super_admin|founder|ceo|coo|sales'])->group(function () {
            // Dashboard
            Route::get('dashboard', [InstitutionalDashboardController::class, 'index']);

            // Organizations (Admin/Sales only for creation, Inst Admin for management)
            Route::post('corporates/import', [CorporateController::class, 'import']);
            Route::post('corporates/{corporate}/pilot/activate', [CorporateController::class, 'activatePilot']);
            Route::apiResource('corporates', CorporateController::class);
            Route::post('universities/import', [UniversityController::class, 'import']);
            Route::apiResource('universities', UniversityController::class);

            // Generic Organization routes
            Route::get('organizations/{organization}', [InstitutionalOrganizationController::class, 'show']);
            Route::put('organizations/{organization}', [InstitutionalOrganizationController::class, 'update']);

            // Members Management
            Route::prefix('organizations/{organization}/members')->group(function () {
                Route::get('/', [InstitutionalMemberController::class, 'index']);
                Route::post('/', [InstitutionalMemberController::class, 'store']);
                Route::put('/{user}', [InstitutionalMemberController::class, 'update']);
                Route::post('/import', [InstitutionalMemberController::class, 'bulkImport']); // Bulk import
                Route::delete('/{user}', [InstitutionalMemberController::class, 'destroy']);
            });

            // Reports
            Route::get('reports', [InstitutionalReportController::class, 'index']);

            // Referrals
            Route::get('referrals', [InstitutionalReferralController::class, 'index']);
            Route::post('referrals', [InstitutionalReferralController::class, 'store']);

            // Documents
            Route::get('documents', [InstitutionalDocumentController::class, 'index']);

            // Branding
            Route::get('organizations/{organization}/branding', [InstitutionalOrganizationController::class, 'getBranding']);
            Route::put('organizations/{organization}/branding', [InstitutionalOrganizationController::class, 'updateBranding']);

            // Subscriptions
            Route::get('organizations/{organization}/subscription', [InstitutionalSubscriptionController::class, 'show']);

            // Analytics
            Route::get('organizations/{organization}/analytics/engagement', [\App\Http\Controllers\API\V1\Institutional\AnalyticsController::class, 'engagement']);
            Route::get('organizations/{organization}/analytics/at-risk', [\App\Http\Controllers\API\V1\Institutional\AnalyticsController::class, 'atRisk']);
            Route::get('organizations/{organization}/analytics/monthly-report', [\App\Http\Controllers\API\V1\Institutional\AnalyticsController::class, 'monthlyReport']);

            // Quota Usage (Admin View)
            Route::get('quota-usage', [\App\Http\Controllers\API\V1\Institutional\QuotaUsageController::class, 'index']);
        });

        // Finance Routes ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â restricted to finance roles only
        Route::prefix('finance')->middleware(['auth:sanctum', 'role:finance|cfo|ceo|president|admin|super_admin|founder'])->group(function () {
            Route::get('dashboard', [FinanceDashboardController::class, 'index']);
            Route::get('stats', [FinanceDashboardController::class, 'stats']);
            Route::get('pnl', [FinanceDashboardController::class, 'pnl']);
            Route::get('cac', [FinanceDashboardController::class, 'cac']);
            Route::get('ltv', [FinanceDashboardController::class, 'ltv']);
            Route::apiResource('invoices', FinanceInvoiceController::class, ['as' => 'finance.auth']);
            Route::apiResource('payouts', FinancePayoutController::class, ['as' => 'finance.auth']);
            Route::post('payouts/{id}/process', [FinancePayoutController::class, 'process']);
            Route::get('revenue', [FinanceRevenueController::class, 'index']);
            // Three Pillars of Finance
            Route::get('balance-sheet',    [\App\Http\Controllers\API\V1\Finance\FinancialStatementsController::class, 'balanceSheet']);
            Route::get('income-statement', [\App\Http\Controllers\API\V1\Finance\FinancialStatementsController::class, 'incomeStatement']);
            Route::get('cash-flow',        [\App\Http\Controllers\API\V1\Finance\FinancialStatementsController::class, 'cashFlow']);
        });

        // Admin routes
        Route::prefix('admin')->middleware(['role:admin|ceo|super_admin|founder'])->group(function () {
            Route::get('dashboard', [AdminDashboardController::class, 'index']);
            Route::get('dashboard/revenue-flow', [AdminDashboardController::class, 'revenueFlow']);
            Route::get('dashboard/lead-sources', [AdminDashboardController::class, 'leadSources']);
            Route::get('dashboard/deals', [AdminDashboardController::class, 'deals']);

            Route::get('security/devices', [\App\Http\Controllers\API\V1\Admin\DeviceFingerprintController::class, 'index']);

            // Referral Management
            Route::prefix('referrals')->group(function () {
                Route::get('/', [\App\Http\Controllers\API\V1\Admin\AdminReferralController::class, 'index']);
                Route::get('/stats', [\App\Http\Controllers\API\V1\Admin\AdminReferralController::class, 'stats']);
                Route::get('/ambassadors', [\App\Http\Controllers\API\V1\Admin\AdminReferralController::class, 'ambassadors']);
                Route::get('/leaderboard', [\App\Http\Controllers\API\V1\Admin\AdminReferralController::class, 'leaderboard']);
                Route::get('/rewards', [\App\Http\Controllers\API\V1\Admin\AdminReferralController::class, 'rewards']);
                Route::get('/{id}', [\App\Http\Controllers\API\V1\Admin\AdminReferralController::class, 'show']);
                Route::put('/{id}/status', [\App\Http\Controllers\API\V1\Admin\AdminReferralController::class, 'updateStatus']);
                Route::put('/rewards/{id}/status', [\App\Http\Controllers\API\V1\Admin\AdminReferralController::class, 'updateRewardStatus']);
            });

            // Role & Permission Management
            Route::get('permissions', [RoleController::class, 'permissions']);
            Route::apiResource('roles', RoleController::class);

            // User Management
            Route::get('users', [AdminUserManagementController::class, 'index']);
            Route::post('users', [AdminUserManagementController::class, 'store']);
            Route::get('users/{user}', [AdminUserManagementController::class, 'show']);
            Route::put('users/{user}', [AdminUserManagementController::class, 'update']);
            Route::patch('users/{user}', [AdminUserManagementController::class, 'update']);
            Route::post('users/{user}/suspend', [AdminUserManagementController::class, 'suspend']);
            Route::post('users/{user}/activate', [AdminUserManagementController::class, 'activate']);
            Route::delete('users/{user}', [AdminUserManagementController::class, 'destroy']);
            Route::post('users/{user}/roles', [AdminUserManagementController::class, 'updateRoles']);
            Route::get('student-verifications', [AdminUserManagementController::class, 'getStudentVerifications']);

            // Therapist Management
            Route::get('therapists', [AdminTherapistController::class, 'index']);
            Route::get('therapists/location-flags', [AdminTherapistController::class, 'locationFlags']);
            Route::get('therapists/{therapist}', [AdminTherapistController::class, 'show']);
            Route::post('therapists/{therapist}/deactivate', [AdminTherapistController::class, 'deactivate']);
            Route::post('therapists/{therapist}/activate', [AdminTherapistController::class, 'activate']);
            Route::post('therapists/{therapist}/resolve-location-flag', [AdminTherapistController::class, 'resolveLocationFlag']);

            // Therapist Invites
            Route::post('therapists/invite', [\App\Http\Controllers\API\V1\Admin\TherapistInviteController::class, 'store']);
            Route::get('therapists/invites', [\App\Http\Controllers\API\V1\Admin\TherapistInviteController::class, 'index']);
            Route::delete('therapists/invites/{invite}', [\App\Http\Controllers\API\V1\Admin\TherapistInviteController::class, 'destroy']);

            // Reports
            Route::get('reports/financial', [AdminReportController::class, 'financial']);
            Route::get('reports/user-growth', [AdminReportController::class, 'userGrowth']);

            // Admin Notifications
            Route::get('notifications', [AdminNotificationController::class, 'index']);
            Route::get('notifications/unread-count', [AdminNotificationController::class, 'unreadCount']);
            Route::patch('notifications/{id}/read', [AdminNotificationController::class, 'markAsRead']);
            Route::patch('notifications/read-all', [AdminNotificationController::class, 'markAsRead']);
            Route::delete('notifications/{id}', [AdminNotificationController::class, 'destroy']);

            // Admin AI Chat
            Route::prefix('ai')->group(function () {
                Route::post('chat', [AdminAIChatController::class, 'chat']);
                Route::get('conversations', [AdminAIChatController::class, 'getConversations']);
                Route::get('conversations/{conversation_id}', [AdminAIChatController::class, 'getConversation']);
                Route::delete('conversations/{conversation_id}', [AdminAIChatController::class, 'deleteConversation']);
                Route::delete('conversations', [AdminAIChatController::class, 'clearAllConversations']);
            });

            // Corporate Account Management (Admin)
            Route::prefix('corporates')->group(function () {
                Route::get('/', [AdminCorporateController::class, 'index']);
                Route::post('/{corporate}/send-lifecycle-email', [AdminCorporateController::class, 'sendLifecycleEmail']);
                Route::post('/{corporate}/extend-pilot', [AdminCorporateController::class, 'extendPilot']);
                Route::post('/{corporate}/convert-to-paid', [AdminCorporateController::class, 'convertToPaid']);
            });

            // Promotional Codes (Admin CRUD)
            Route::prefix('promo-codes')->group(function () {
                Route::get('/', [AdminPromotionalCodeController::class, 'index']);
                Route::post('/', [AdminPromotionalCodeController::class, 'store']);
                Route::get('/{uuid}', [AdminPromotionalCodeController::class, 'show']);
                Route::put('/{uuid}', [AdminPromotionalCodeController::class, 'update']);
                Route::delete('/{uuid}', [AdminPromotionalCodeController::class, 'destroy']);
                Route::post('/{uuid}/toggle', [AdminPromotionalCodeController::class, 'toggle']);
                Route::get('/{uuid}/stats', [AdminPromotionalCodeController::class, 'stats']);
            });

            Route::apiResource('sessions', AdminSessionController::class, ['as' => 'admin']);

            // AI session audit
            Route::prefix('sessions')->group(function () {
                Route::get('{uuid}/audit',         [SessionAuditController::class, 'show']);
                Route::post('{uuid}/audit/review', [SessionAuditController::class, 'review']);
            });
            Route::get('session-audits', [SessionAuditController::class, 'index']);
            Route::apiResource('content', AdminContentController::class);

            // Auth sessions (Sanctum token management)
            Route::prefix('auth-sessions')->group(function () {
                Route::get('/',              [AuthSessionController::class, 'index']);
                Route::delete('/{id}',       [AuthSessionController::class, 'destroy'])->where('id', '[0-9]+');
                Route::delete('/user/{userId}', [AuthSessionController::class, 'revokeUser']);
            });

            // Landing Page Content Management
            Route::apiResource('landing-page-content', AdminLandingPageContentController::class);
            Route::get('landing-page-content/section/{section}', [AdminLandingPageContentController::class, 'getBySection']);
            Route::post('landing-page-content/bulk-update', [AdminLandingPageContentController::class, 'bulkUpdate']);

            Route::get('settings', [AdminSettingsController::class, 'index']);
            Route::put('settings', [AdminSettingsController::class, 'update']);
            // Specific routes before the {group} wildcard to prevent shadowing
            Route::post('settings/vat/toggle', [AdminSettingsController::class, 'toggleVat']);
            Route::put('settings/vat/rate', [AdminSettingsController::class, 'updateVatRate']);
            Route::put('settings/booking-fee', [AdminSettingsController::class, 'updateBookingFee']);
            Route::post('settings/ambassador-referral/toggle', [AdminSettingsController::class, 'toggleAmbassadorReferralTracking']);
            Route::put('settings/{group}', [AdminSettingsController::class, 'updateGroup']);

            // User-to-user referral management
            Route::get('user-referrals', [\App\Http\Controllers\API\V1\Admin\UserReferralController::class, 'index']);
            Route::get('user-referrals/stats', [\App\Http\Controllers\API\V1\Admin\UserReferralController::class, 'stats']);
            Route::get('referral-reward-configs', [\App\Http\Controllers\API\V1\Admin\UserReferralController::class, 'configs']);
            Route::put('referral-reward-configs/{id}', [\App\Http\Controllers\API\V1\Admin\UserReferralController::class, 'updateConfig']);

            // WhatsApp channel management
            Route::prefix('whatsapp')->group(function () {
                Route::get('status',         [AdminWhatsAppController::class, 'status']);
                Route::get('qr',             [AdminWhatsAppController::class, 'qr']);
                Route::post('disconnect',    [AdminWhatsAppController::class, 'disconnect']);
                Route::put('provider',       [AdminWhatsAppController::class, 'setProvider']);
            });

            // Audit logs
            Route::get('audit-logs', function (\Illuminate\Http\Request $request) {
                $logs = \App\Models\Admin\AdminLog::with('user:id,first_name,last_name,email')
                    ->when($request->action, fn ($q, $a) => $q->where('action', $a))
                    ->when($request->user_id, fn ($q, $id) => $q->where('user_id', $id))
                    ->when($request->target_type, fn ($q, $t) => $q->where('target_type', $t))
                    ->latest()
                    ->paginate($request->per_page ?? 20);

                return response()->json(['success' => true, 'data' => $logs, 'message' => 'Audit logs retrieved.']);
            });

            Route::middleware('role:admin|ceo|coo')->group(function () {
                Route::get('ip-protection', [\App\Http\Controllers\API\V1\Admin\IPProtectionController::class, 'index']);
                Route::post('ip-protection', [\App\Http\Controllers\API\V1\Admin\IPProtectionController::class, 'update']);
            });
            Route::middleware('role:admin|ceo')->group(function () {
                Route::get('ip-protection/logs', [\App\Http\Controllers\API\V1\Admin\IPProtectionController::class, 'getLogs']);
            });

            // Subscription Plans
            Route::post('subscription-plans/{id}/toggle-active', [SubscriptionPlanController::class, 'toggleActive']);
            Route::apiResource('subscription-plans', SubscriptionPlanController::class, ['as' => 'admin']);
            // Frontend compatibility alias
            Route::prefix('subscriptions')->group(function () {
                Route::post('plans/{id}/toggle-active', [SubscriptionPlanController::class, 'toggleActive']);
                Route::apiResource('plans', SubscriptionPlanController::class, ['as' => 'admin.subs']);
            });

            // University configuration (admin-only)
            Route::get('universities/{organization}/config', [UniversityConfigController::class, 'show']);
            Route::put('universities/{organization}/config', [UniversityConfigController::class, 'update']);

            // User Subscriptions list (admin view of who has which plan)
            Route::get('user-subscriptions', [AdminSubscriptionListController::class, 'index']);

            // Subscription upgrade approvals
            Route::get('subscription-upgrade/requests', [\App\Http\Controllers\API\V1\Admin\SubscriptionUpgradeApprovalController::class, 'index']);
            Route::get('subscription-upgrade/requests/stats', [\App\Http\Controllers\API\V1\Admin\SubscriptionUpgradeApprovalController::class, 'stats']);
            Route::post('subscription-upgrade/requests/{id}/approve', [\App\Http\Controllers\API\V1\Admin\SubscriptionUpgradeApprovalController::class, 'approve']);
            Route::post('subscription-upgrade/requests/{id}/deny', [\App\Http\Controllers\API\V1\Admin\SubscriptionUpgradeApprovalController::class, 'deny']);

            // Manual subscription upgrade by Admin
            Route::post('users/{user}/subscription/upgrade', [\App\Http\Controllers\API\V1\Admin\AdminUserSubscriptionController::class, 'upgrade']);

            // Quota Settings
            Route::get('quota-settings', [QuotaSettingController::class, 'show']);
            Route::put('quota-settings', [QuotaSettingController::class, 'update']);
            Route::get('quota/overview', [QuotaSettingController::class, 'overview']);

            // User Quota Management
            Route::get('users/{user}/quota', [QuotaSettingController::class, 'getUserQuota']);
            Route::put('users/{user}/quota', [QuotaSettingController::class, 'updateUserQuota']);
            Route::post('users/{user}/quota/reset', [QuotaSettingController::class, 'resetUserQuota']);

            // Distress Overrides ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â users with active manual quota overrides
            Route::get('distress-overrides', [QuotaSettingController::class, 'distressOverrides']);
            Route::delete('distress-overrides/{user}', [QuotaSettingController::class, 'revokeDistressOverride']);

            // Waitlist Management
            Route::get('waitlist', [\App\Http\Controllers\API\V1\WaitlistController::class, 'index']);
            Route::get('waitlist/export', [\App\Http\Controllers\API\V1\WaitlistController::class, 'export']);
            Route::post('waitlist/batch-invite', [\App\Http\Controllers\API\V1\WaitlistController::class, 'batchInvite']);
            Route::patch('waitlist/{waitlist}/invite', [\App\Http\Controllers\API\V1\WaitlistController::class, 'invite']);
            Route::patch('waitlist/{waitlist}/status', [\App\Http\Controllers\API\V1\WaitlistController::class, 'updateStatus']);
            Route::delete('waitlist/{waitlist}', [\App\Http\Controllers\API\V1\WaitlistController::class, 'destroy']);

            // Web Analytics & IP Blocks
            Route::get('analytics/overview', [\App\Http\Controllers\API\V1\Admin\AnalyticsController::class, 'overview']);
            Route::get('analytics/sessions', [\App\Http\Controllers\API\V1\Admin\AnalyticsController::class, 'sessions']);
            Route::get('analytics/ip-blocks', [\App\Http\Controllers\API\V1\Admin\AnalyticsController::class, 'listBlocks']);
            Route::post('analytics/ip-blocks', [\App\Http\Controllers\API\V1\Admin\AnalyticsController::class, 'addBlock']);
            Route::patch('analytics/ip-blocks/{block}/deactivate', [\App\Http\Controllers\API\V1\Admin\AnalyticsController::class, 'removeBlock']);
            Route::delete('analytics/ip-blocks/{block}', [\App\Http\Controllers\API\V1\Admin\AnalyticsController::class, 'deleteBlock']);

            // Payout Settings
            Route::get('payout-settings', [\App\Http\Controllers\API\V1\Admin\PayoutSettingController::class, 'index']);
            Route::patch('payout-settings/{role}', [\App\Http\Controllers\API\V1\Admin\PayoutSettingController::class, 'update']);

            // Vulnerability Reports
            Route::get('security/reports', [\App\Http\Controllers\API\V1\Admin\VulnerabilityReportController::class, 'index']);
            Route::get('security/reports/{report}', [\App\Http\Controllers\API\V1\Admin\VulnerabilityReportController::class, 'show']);
            Route::patch('security/reports/{report}', [\App\Http\Controllers\API\V1\Admin\VulnerabilityReportController::class, 'update']);
            Route::delete('security/reports/{report}', [\App\Http\Controllers\API\V1\Admin\VulnerabilityReportController::class, 'destroy']);

            // Mail Logs
            Route::get('mail-logs', [\App\Http\Controllers\API\V1\Admin\MailLogController::class, 'index']);
            Route::delete('mail-logs/{mailLog}', [\App\Http\Controllers\API\V1\Admin\MailLogController::class, 'destroy']);
            Route::delete('mail-logs', [\App\Http\Controllers\API\V1\Admin\MailLogController::class, 'purge']);

            // Contact Submissions
            Route::get('contact-submissions', [\App\Http\Controllers\API\V1\Admin\ContactSubmissionController::class, 'index']);
            Route::get('contact-submissions/{contact}', [\App\Http\Controllers\API\V1\Admin\ContactSubmissionController::class, 'show']);
            Route::patch('contact-submissions/{contact}/status', [\App\Http\Controllers\API\V1\Admin\ContactSubmissionController::class, 'updateStatus']);
            Route::post('contact-submissions/{contact}/notes', [\App\Http\Controllers\API\V1\Admin\ContactSubmissionController::class, 'addNote']);
            Route::delete('contact-submissions/{contact}', [\App\Http\Controllers\API\V1\Admin\ContactSubmissionController::class, 'destroy']);

            // Feedback Management
            Route::get('feedback', [FeedbackController::class, 'index']);
            Route::get('feedback/{id}', [FeedbackController::class, 'show']);
            Route::put('feedback/{id}/status', [FeedbackController::class, 'updateStatus']);
            Route::delete('feedback/{id}', [FeedbackController::class, 'destroy']);

            // Sounds (Audio resources)
            Route::get('sounds', [\App\Http\Controllers\API\V1\Admin\SoundController::class, 'index']);
            Route::post('sounds', [\App\Http\Controllers\API\V1\Admin\SoundController::class, 'store']);
            Route::delete('sounds/{filename}', [\App\Http\Controllers\API\V1\Admin\SoundController::class, 'destroy']);

            // Maintenance Management
            Route::get('maintenance', [AdminMaintenanceController::class, 'index']);
            Route::post('maintenance/{id}/approve', [AdminMaintenanceController::class, 'approve']);
            Route::post('maintenance/{id}/reject', [AdminMaintenanceController::class, 'reject']);
            Route::post('maintenance/{id}/complete', [AdminMaintenanceController::class, 'complete']);

            Route::apiResource('courses', AdminCourseController::class);
            Route::apiResource('communities', AdminCommunityController::class);

            // Career / Job Postings Management
            Route::prefix('careers')->group(function () {
                Route::get('/', [\App\Http\Controllers\API\V1\Admin\CareerController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\API\V1\Admin\CareerController::class, 'store']);
                Route::get('{id}', [\App\Http\Controllers\API\V1\Admin\CareerController::class, 'show']);
                Route::put('{id}', [\App\Http\Controllers\API\V1\Admin\CareerController::class, 'update']);
                Route::delete('{id}', [\App\Http\Controllers\API\V1\Admin\CareerController::class, 'destroy']);
                Route::post('{id}/toggle', [\App\Http\Controllers\API\V1\Admin\CareerController::class, 'toggle']);
                Route::patch('{id}/status', [\App\Http\Controllers\API\V1\Admin\CareerController::class, 'setStatus']);
                // Job Applications
                Route::get('applications', [JobApplicationController::class, 'index']);
                Route::get('applications/recent', [JobApplicationController::class, 'recent']);
                Route::get('applications/{uuid}', [JobApplicationController::class, 'show']);
                Route::patch('applications/{uuid}', [JobApplicationController::class, 'update']);
                Route::post('applications/{uuid}/onboard', [JobApplicationController::class, 'onboard']);
                Route::delete('applications/{uuid}', [JobApplicationController::class, 'destroy']);
            });

            // Editorial Article Management
            Route::prefix('editorial')->group(function () {
                Route::get('posts', [\App\Http\Controllers\API\V1\Admin\EditorialPostController::class, 'index']);
                Route::post('posts', [\App\Http\Controllers\API\V1\Admin\EditorialPostController::class, 'store']);
                Route::get('posts/{id}', [\App\Http\Controllers\API\V1\Admin\EditorialPostController::class, 'show']);
                Route::put('posts/{id}', [\App\Http\Controllers\API\V1\Admin\EditorialPostController::class, 'update']);
                Route::delete('posts/{id}', [\App\Http\Controllers\API\V1\Admin\EditorialPostController::class, 'destroy']);
                Route::post('posts/{id}/publish', [\App\Http\Controllers\API\V1\Admin\EditorialPostController::class, 'publish']);
                Route::post('posts/{id}/unpublish', [\App\Http\Controllers\API\V1\Admin\EditorialPostController::class, 'unpublish']);
                Route::post('posts/{id}/image', [\App\Http\Controllers\API\V1\Admin\EditorialPostController::class, 'uploadImage']);
                Route::get('categories', [\App\Http\Controllers\API\V1\Admin\EditorialPostController::class, 'categories']);
                Route::post('categories', [\App\Http\Controllers\API\V1\Admin\EditorialPostController::class, 'storeCategory']);
                Route::delete('categories/{id}', [\App\Http\Controllers\API\V1\Admin\EditorialPostController::class, 'destroyCategory']);
            });
        });

        // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Calendar Events (shared across admin/CEO/COO/sales roles) ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
        Route::middleware(['auth:sanctum', 'role:admin|ceo|coo|cgo|sales|finder|closer|builder'])->group(function () {
            Route::apiResource('calendar/events', \App\Http\Controllers\API\V1\CalendarEventController::class)
                ->parameters(['events' => 'calendarEvent']);
        });

        // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Demo Lead management (admin/CEO/COO assign calls to sales reps) ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
        Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin|ceo|coo|super_admin'])->group(function () {
            Route::get('demo-leads', [\App\Http\Controllers\API\V1\Admin\DemoLeadController::class, 'index']);
            Route::post('leads/{lead}/assign-demo', [\App\Http\Controllers\API\V1\Admin\DemoLeadController::class, 'assignDemo']);

            // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Employee Salary Management ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
            Route::get('employee-salaries/summary', [\App\Http\Controllers\API\V1\Admin\EmployeeSalaryController::class, 'summary']);
            Route::get('employee-salaries',          [\App\Http\Controllers\API\V1\Admin\EmployeeSalaryController::class, 'index']);
            Route::post('employee-salaries',         [\App\Http\Controllers\API\V1\Admin\EmployeeSalaryController::class, 'store']);
            Route::put('employee-salaries/{id}',     [\App\Http\Controllers\API\V1\Admin\EmployeeSalaryController::class, 'update']);
            Route::delete('employee-salaries/{id}',  [\App\Http\Controllers\API\V1\Admin\EmployeeSalaryController::class, 'destroy']);
        });

        // COO & CGO specific routes
        Route::prefix('coo')->middleware(['auth:sanctum', 'role:coo|vp_operations|cgo|ceo|admin'])->group(function () {
            Route::get('operations-overview', [COOController::class, 'operationsOverview']);
            Route::get('ai-operations', [COOController::class, 'aiOperations']);

            // Operational Logs
            Route::get('operational-logs', [COOController::class, 'listOperationalLogs']);
            Route::post('operational-logs', [COOController::class, 'storeOperationalLog']);
            Route::patch('operational-logs/{id}', [COOController::class, 'updateOperationalLog']);
            Route::delete('operational-logs/{id}', [COOController::class, 'destroyOperationalLog']);

            // Marketing funnel
            Route::get('marketing/funnel', [COOController::class, 'marketingFunnel']);
        });

        // Mail Routes
        Route::prefix('admin/mail')->middleware(['auth:sanctum', 'role:admin,coo,ceo'])
            ->group(function () {
                Route::get('inbox', [MailController::class, 'inbox']);
                Route::get('folders', [MailController::class, 'folders']);
                Route::get('unread-count', [MailController::class, 'unreadCount']);
                Route::get('{id}', [MailController::class, 'show']);
                Route::post('send', [MailController::class, 'send']);
                Route::post('test-connection', [MailController::class, 'testConnection']);
                Route::patch('{id}/read', [MailController::class, 'markRead']);
                Route::delete('{id}', [MailController::class, 'trash']);
            });

        // Notification routes are served via /patient/notifications/* and /therapist/notifications/* prefixes.
        // See the patient and therapist route groups above.

        // Shared Admin & Clinical Advisor Routes
        Route::prefix('admin')->middleware(['role:admin|clinical_advisor'])->group(function () {
            // Therapist Verification
            Route::get('therapists/pending', [AdminTherapistVerificationController::class, 'index']);
            Route::post('therapists/{therapist}/approve', [AdminTherapistVerificationController::class, 'approve']);
            Route::post('therapists/{therapist}/reject', [AdminTherapistVerificationController::class, 'reject']);
            Route::get('therapists/{therapist}/documents/{type}', [AdminTherapistVerificationController::class, 'viewDocument']);
        });

        // General Admin Routes
        Route::prefix('admin')->middleware(['role:admin'])->group(function () {
            // Settings ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â specific routes before {section} wildcard
            Route::get('settings', [AdminSettingsController::class, 'index']);
            Route::post('settings/vat/toggle', [AdminSettingsController::class, 'toggleVat']);
            Route::put('settings/vat/rate', [AdminSettingsController::class, 'updateVatRate']);
            Route::put('settings/booking-fee', [AdminSettingsController::class, 'updateBookingFee']);
            Route::put('settings/{section}', [AdminSettingsController::class, 'updateGroup']);

            // Subscription Plans
            Route::apiResource('subscription-plans', SubscriptionPlanController::class, ['as' => 'admin2']);

            // Center Management
            Route::get('centers/stats', [AdminCenterController::class, 'stats']);
            Route::get('centers/available-managers', [AdminCenterController::class, 'availableManagers']);
            Route::apiResource('centers', AdminCenterController::class, ['as' => 'admin']);

            // Inventory Management
            Route::get('inventory/stats', [AdminInventoryController::class, 'stats']);
            Route::get('inventory/equipment-types', [AdminInventoryController::class, 'equipmentTypes']);
            Route::post('inventory/bulk-update-status', [AdminInventoryController::class, 'bulkUpdateStatus']);
            Route::apiResource('inventory', AdminInventoryController::class, ['as' => 'admin']);

            // Booking Management
            Route::get('bookings/overview', [AdminBookingController::class, 'overview']);
            Route::get('bookings/by-center', [AdminBookingController::class, 'byCenter']);
            Route::get('bookings/trends', [AdminBookingController::class, 'trends']);
            Route::get('bookings/occupancy-by-hour', [AdminBookingController::class, 'occupancyByHour']);
            Route::post('bookings/{booking}/status', [AdminBookingController::class, 'updateStatus']);
            Route::apiResource('bookings', AdminBookingController::class, ['as' => 'admin'])->only(['show']);

            // Revenue Management
            Route::get('revenue/by-center', [AdminRevenueController::class, 'byCenter']);
            Route::get('revenue/monthly-trends', [AdminRevenueController::class, 'monthlyTrends']);
            Route::get('revenue/by-service', [AdminRevenueController::class, 'byService']);
            Route::get('revenue/analytics', [AdminRevenueController::class, 'analytics']);
            Route::get('revenue/export', [AdminRevenueController::class, 'export']);
            Route::get('revenue/breakdown', [AdminRevenueController::class, 'getRevenueBreakdown']);
            Route::get('revenue/full-export', [AdminRevenueController::class, 'fullExport']);

            // Live active-user stats (admin only)
            Route::get('map/active-users', [\App\Http\Controllers\API\V1\MapController::class, 'activeUsers']);

            // Regional matching algorithm toggle
            Route::get('matching/state', [\App\Http\Controllers\API\V1\Admin\MatchingController::class, 'getState']);
            Route::post('matching/state', [\App\Http\Controllers\API\V1\Admin\MatchingController::class, 'setState']);
        });

        // Company Resource Management (Admin, Manager, Data Entry)
        Route::prefix('admin')->middleware(['role:admin|manager|data_entry'])->group(function () {
            // Mindful Resources Management
            Route::post('resources/{id}/approve', [AdminResourceController::class, 'approve']);
            Route::post('resources/{id}/reject', [AdminResourceController::class, 'reject']);
            Route::apiResource('resources', AdminResourceController::class, ['as' => 'admin.company']);
            Route::post('resources/categories', [AdminResourceController::class, 'storeCategory']);
        });

        // Employee routes
        Route::prefix('employee')->middleware(['role:employee'])->group(function () {
            Route::get('dashboard', [EmployeeDashboardController::class, 'index']);
            Route::apiResource('tasks', EmployeeTaskController::class, ['as' => 'employee']);
            Route::get('timesheet', [EmployeeTimeSheetController::class, 'index']);
            Route::post('timesheet', [EmployeeTimeSheetController::class, 'store']);
            Route::post('clock-in', [EmployeeTimeSheetController::class, 'clockIn']);
            Route::post('clock-out', [EmployeeTimeSheetController::class, 'clockOut']);
        });

        // HR routes
        Route::prefix('hr')->middleware(['role:hr'])->group(function () {
            Route::get('dashboard', [HRDashboardController::class, 'index']);
            Route::get('stats', [HRDashboardController::class, 'stats']);
            Route::get('financial-flow', [HRDashboardController::class, 'financialFlow']);
            Route::apiResource('employees', HREmployeeController::class);
            Route::get('payroll', [HRPayrollController::class, 'index']);
            Route::post('payroll', [HRPayrollController::class, 'store']);
            Route::post('payroll/process', [HRPayrollController::class, 'process']);
            Route::post('payroll/{uuid}/mark-paid', [HRPayrollController::class, 'markPaid']);
            Route::get('leaves', [HRLeaveController::class, 'index']);
            Route::put('leaves/{id}', [HRLeaveController::class, 'update']);
            // Benefits
            Route::get('benefits', [\App\Http\Controllers\API\V1\HR\BenefitsController::class, 'index']);
            Route::post('benefits', [\App\Http\Controllers\API\V1\HR\BenefitsController::class, 'store']);
            Route::put('benefits/{benefit}', [\App\Http\Controllers\API\V1\HR\BenefitsController::class, 'update']);
            Route::delete('benefits/{benefit}', [\App\Http\Controllers\API\V1\HR\BenefitsController::class, 'destroy']);
            // Job Applications (HR read + manage)
            Route::get('careers/applications', [JobApplicationController::class, 'index']);
            Route::get('careers/applications/recent', [JobApplicationController::class, 'recent']);
            Route::get('careers/applications/{uuid}', [JobApplicationController::class, 'show']);
            Route::patch('careers/applications/{uuid}', [JobApplicationController::class, 'update']);
            Route::post('careers/applications/{uuid}/onboard', [JobApplicationController::class, 'onboard']);
        });

        // Ambassador application (public - patients can apply)
        Route::post('ambassador/apply', [\App\Http\Controllers\API\V1\Patient\AmbassadorController::class, 'apply'])->middleware(['auth:sanctum', 'verified']);

        // Ambassador routes (ambassador only)
        Route::prefix('ambassador')->middleware(['auth:sanctum', 'ambassador'])->group(function () {
            Route::get('dashboard', [AmbassadorDashboardController::class, 'index']);
            Route::get('referrals', [AmbassadorReferralController::class, 'index']);
            Route::get('payouts', [AmbassadorPayoutController::class, 'index']);
            Route::post('payouts', [AmbassadorPayoutController::class, 'requestPayout']);
            Route::get('referral-code', [\App\Http\Controllers\API\V1\Patient\AmbassadorController::class, 'generateReferralCode']);
            Route::get('stats', [\App\Http\Controllers\API\V1\Patient\AmbassadorController::class, 'stats']);
        });

        // Manager routes
        Route::prefix('manager')->middleware(['role:manager'])->group(function () {
            Route::get('dashboard', [ManagerDashboardController::class, 'index']);
            Route::apiResource('team', ManagerTeamController::class);
            Route::apiResource('inventory', ManagerInventoryController::class, ['as' => 'manager']);
            Route::apiResource('schedules', ManagerScheduleController::class);
            Route::get('reports', [ManagerReportController::class, 'index']);

            // Sounds (Audio resources)
            Route::get('sounds', [\App\Http\Controllers\API\V1\Manager\SoundController::class, 'index']);
            Route::post('sounds', [\App\Http\Controllers\API\V1\Manager\SoundController::class, 'store']);
            Route::delete('sounds/{filename}', [\App\Http\Controllers\API\V1\Manager\SoundController::class, 'destroy']);

            // Subscription upgrade requests (to be approved by admin)
            Route::post('subscription-upgrade/requests', [\App\Http\Controllers\API\V1\Manager\SubscriptionUpgradeRequestController::class, 'store']);
        });

        // Admin read-only access to support tickets
        Route::prefix('admin/support')->middleware(['role:admin'])->group(function () {
            Route::get('tickets', [\App\Http\Controllers\API\V1\Support\TicketController::class, 'index']);
            Route::get('tickets/{ticket}', [\App\Http\Controllers\API\V1\Support\TicketController::class, 'show']);
            Route::get('stats', [\App\Http\Controllers\API\V1\Support\DashboardController::class, 'index']);
        });

        // Support routes
        Route::prefix('support')->middleware(['role:support'])->group(function () {
            Route::get('dashboard', [SupportDashboardController::class, 'index']);
            Route::get('stats', [SupportDashboardController::class, 'index']);
            Route::apiResource('tickets', SupportTicketController::class);

            // Agent-only live chat endpoints
            Route::prefix('chats')->group(function () {
                Route::get('active', [SupportChatController::class, 'activeChats']);
                Route::post('{uuid}/agent-reply', [SupportChatController::class, 'agentMessage']);
                Route::post('{uuid}/handover', [SupportChatController::class, 'handover']);
                Route::post('{uuid}/release', [SupportChatController::class, 'release']);
                Route::post('{uuid}/close', [SupportChatController::class, 'close']);
                Route::get('{uuid}', [SupportChatController::class, 'show']);
            });
        });

        // Customer-facing live support chat (any authenticated user)
        Route::prefix('support/chats')->group(function () {
            Route::post('start', [SupportChatController::class, 'start']);
            Route::post('{uuid}/message', [SupportChatController::class, 'sendMessage']);
            Route::get('{uuid}', [SupportChatController::class, 'show']);
            Route::post('{uuid}/close', [SupportChatController::class, 'close']);
        });

        // Sales routes
        Route::prefix('sales')->middleware(['role:sales|vp_sales'])->group(function () {
            Route::get('dashboard', [SalesDashboardController::class, 'index']);
            Route::get('stats', [SalesDashboardController::class, 'stats']);
            Route::get('notifications', [SalesNotificationController::class, 'index']);
            // Outreach email (send on behalf of the sales rep)
            Route::post('mail/send', [\App\Http\Controllers\API\V1\Sales\OutreachController::class, 'sendEmail']);
            // AI assistant ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â delegates to the same AdminAIChatController used by admin, scoped to sales context
            Route::post('ai/chat', [\App\Http\Controllers\API\V1\Admin\AdminAIChatController::class, 'chat']);
            Route::get('revenue-flow', [SalesDashboardController::class, 'revenueFlow']);
            Route::get('lead-sources', [SalesDashboardController::class, 'leadSources']);
            Route::get('waitlist', [\App\Http\Controllers\API\V1\Sales\WaitlistController::class, 'index']);
            Route::get('waitlist/export', [\App\Http\Controllers\API\V1\Sales\WaitlistController::class, 'export']);

            // Lead management extensions
            Route::post('leads/{id}/assign-me', [SalesLeadController::class, 'assignMe']);
            Route::post('leads/{id}/handoff', [SalesLeadController::class, 'handoff']);

            Route::apiResource('leads', SalesLeadController::class, ['as' => 'sales']);
            Route::apiResource('deals', \App\Http\Controllers\API\V1\Sales\DealController::class);
            Route::apiResource('contacts', \App\Http\Controllers\API\V1\Sales\ContactController::class);
            Route::apiResource('tasks', \App\Http\Controllers\API\V1\Sales\TaskController::class, ['as' => 'sales']);
            Route::apiResource('pipeline', SalesPipelineController::class);
            // Agent's own territories
            Route::get('my-territories', [\App\Http\Controllers\API\V1\Sales\TerritoryController::class, 'myTerritories']);
            Route::get('territories', [\App\Http\Controllers\API\V1\Sales\TerritoryController::class, 'index']);
        });

        // Closer (Senior AE) routes
        Route::prefix('sales/closer')->middleware(['auth:sanctum', 'role:closer|sales|admin'])->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\API\V1\Sales\CloserDashboardController::class, 'index']);
            Route::get('ready-to-close', [\App\Http\Controllers\API\V1\Sales\CloserDashboardController::class, 'readyToClose']);
            Route::post('deals/{id}/mark-won', [\App\Http\Controllers\API\V1\Sales\CloserDashboardController::class, 'markWon']);
            Route::post('deals/{id}/mark-lost', [\App\Http\Controllers\API\V1\Sales\CloserDashboardController::class, 'markLost']);
        });

        // Admin territory management (admin + sales managers)
        Route::prefix('admin/sales')->middleware(['role:admin|sales'])->group(function () {
            Route::apiResource('territories', \App\Http\Controllers\API\V1\Sales\TerritoryController::class);
            Route::post('territories/{territory}/assign', [\App\Http\Controllers\API\V1\Sales\TerritoryController::class, 'assign']);
            Route::delete('territories/{territory}/agents/{userId}', [\App\Http\Controllers\API\V1\Sales\TerritoryController::class, 'removeAgent']);
        });

        // Marketing routes
        Route::prefix('marketing')->middleware(['role:marketing|vp_marketing|coo|cgo|ceo|admin|super_admin|founder'])->group(function () {
            Route::get('dashboard', [MarketingDashboardController::class, 'index']);
            Route::get('stats', [MarketingDashboardController::class, 'index']);
            Route::post('ai/chat', [\App\Http\Controllers\API\V1\Admin\AdminAIChatController::class, 'chat']);
            Route::get('chart', [MarketingAnalyticsController::class, 'chart']);
            Route::get('lead-sources', [MarketingDashboardController::class, 'leadSources']);
            Route::get('signup-sources', [MarketingDashboardController::class, 'signupSources']);
            Route::apiResource('campaigns', MarketingCampaignController::class);
            Route::get('analytics', [MarketingAnalyticsController::class, 'index']);
            // Allow marketing to view leads
            Route::apiResource('leads', \App\Http\Controllers\API\V1\Marketing\LeadController::class, ['as' => 'marketing']);
        });

        // Secretary routes
        Route::prefix('secretary')->middleware(['role:secretary|admin'])->group(function () {
            Route::get('dashboard', [SecretaryDashboardController::class, 'index']);
            Route::get('stats', [SecretaryDashboardController::class, 'stats']);
            Route::get('chart-data', [SecretaryDashboardController::class, 'chartData']);
            Route::get('tasks', [SecretaryDashboardController::class, 'tasks']);
            Route::get('people', [SecretaryDashboardController::class, 'people']);
            Route::get('documents', [SecretaryDashboardController::class, 'documents']);

            Route::apiResource('calendar', \App\Http\Controllers\API\V1\Secretary\CalendarController::class);
            Route::apiResource('appointments', SecretaryAppointmentController::class);
            Route::apiResource('patients', \App\Http\Controllers\API\V1\Secretary\PatientController::class, ['as' => 'secretary']);

            Route::post('visitors/{id}/checkout', [SecretaryVisitorController::class, 'checkout']);
            Route::apiResource('visitors', SecretaryVisitorController::class);
        });

        // Finance routes
        Route::prefix('finance')->middleware(['role:finance|admin'])->group(function () {
            Route::get('dashboard', [FinanceDashboardController::class, 'index']);
            Route::get('stats', [FinanceDashboardController::class, 'stats']);
            Route::get('transactions', [FinanceDashboardController::class, 'transactions']);
            Route::put('transactions/{id}/reconcile', [FinanceDashboardController::class, 'reconcile']);
            Route::get('expenses', [FinanceDashboardController::class, 'expenses']);
            Route::get('revenue', [FinanceRevenueController::class, 'index']);
            Route::apiResource('invoices', FinanceInvoiceController::class, ['as' => 'finance.role']);
            Route::apiResource('payouts', FinancePayoutController::class, ['as' => 'finance.role']);
            Route::post('payouts/batch', [FinancePayoutController::class, 'batch']);
        });

        // Admin Payment & Refund Management
        Route::prefix('admin/payments')->middleware(['role:admin|finance'])->group(function () {
            Route::get('/', [\App\Http\Controllers\API\V1\Admin\PaymentManagementController::class, 'index']);
            Route::get('refunds', [\App\Http\Controllers\API\V1\Admin\PaymentManagementController::class, 'refunds']);
            Route::post('{payment}/refund', [\App\Http\Controllers\API\V1\Admin\PaymentManagementController::class, 'refund']);
            Route::get('disputes', [\App\Http\Controllers\API\V1\Admin\PaymentManagementController::class, 'disputes']);
            Route::patch('{payment}/dispute', [\App\Http\Controllers\API\V1\Admin\PaymentManagementController::class, 'updateDispute']);
        });

        // Institutional routes
        Route::prefix('institutional')->middleware(['auth:sanctum', 'role:institutional|institution_admin|university_admin|admin|super_admin|founder|ceo|coo|sales'])->group(function () {
            // Subscription & upgrade ? no paywall on these so users can renew
            Route::prefix('organizations/{organization}')->group(function () {
                Route::get('subscription', [InstitutionalSubscriptionController::class, 'show']);
                Route::post('subscription/upgrade', [InstitutionalSubscriptionController::class, 'upgrade']);
            });

            // Paywall-gated routes
            Route::middleware(['institutional.paywall'])->group(function () {
                Route::get('dashboard', [InstitutionalDashboardController::class, 'index']);

                Route::apiResource('organizations', InstitutionalOrganizationController::class);

                Route::prefix('organizations/{organization}')->group(function () {
                    Route::get('members', [InstitutionalMemberController::class, 'index']);
                    Route::post('members/import', [InstitutionalMemberController::class, 'bulkImport']);
                });

                Route::apiResource('corporate', CorporateController::class);
                Route::apiResource('university', UniversityController::class);
                Route::get('reports', [InstitutionalReportController::class, 'index']);
                Route::apiResource('documents', InstitutionalDocumentController::class, ['as' => 'institutional']);
                Route::get('billing/invoices', [InstitutionalSubscriptionController::class, 'invoices']);

                // Employee invite ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â send
                Route::post('organizations/{organization}/invites', [InstitutionalInviteController::class, 'send']);
            });
        });

        // Physical Center routes
        Route::prefix('center')->middleware(['role:center_manager|center'])->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\API\V1\PhysicalCenter\DashboardController::class, 'index']);
            Route::apiResource('centers', CenterController::class, ['as' => 'center']);
            Route::apiResource('bookings', CenterBookingController::class, ['as' => 'center']);
            Route::apiResource('inventory', CenterInventoryController::class, ['as' => 'center']);
            Route::get('reports', [\App\Http\Controllers\API\V1\PhysicalCenter\ReportController::class, 'index']);
            Route::post('check-in', [CheckInController::class, 'store']);
        });

        // Public analytics endpoint for PWA install tracking
        Route::post('analytics/pwa-install', [MetricsController::class, 'trackPWAInstall']);

        // Analytics routes
        Route::prefix('analytics')->middleware(['role:admin|manager'])->group(function () {
            Route::get('metrics', [MetricsController::class, 'index']);
            Route::get('reports', [AnalyticsReportController::class, 'index']);
            Route::get('export', [ExportController::class, 'index']);
        });

        // Authenticated Payment routes
        Route::prefix('payment')->group(function () {
            Route::post('stripe/charge', [StripeController::class, 'charge']);
            Route::post('paystack/initialize', [PaystackController::class, 'initialize']);
            Route::apiResource('subscriptions', PaymentSubscriptionController::class);
        });

        // Unified Payment endpoints
        Route::post('payments/initiate', [PaymentControllerV1::class, 'initiatePayment'])->name('payment.initiate');
        Route::post('payments/initialize', [PaymentControllerV1::class, 'initiatePayment'])->name('payment.initialize');
        Route::post('payments/{payment}/verify', [PaymentControllerV1::class, 'verifyPayment'])->name('payment.verify');
        Route::post('payments/verify', [PaymentControllerV1::class, 'verifyByReference'])->name('payment.verify.by_reference');
        Route::get('payments', [PaymentControllerV1::class, 'getPaymentHistory'])->name('payment.history');
        Route::get('payments/{payment}', [PaymentControllerV1::class, 'getPayment'])->name('payment.show');
        Route::post('payments/{payment}/refund', [PaymentControllerV1::class, 'refundPayment'])->name('payment.refund');

        // Stripe Checkout
        Route::post('payments/stripe/checkout', [StripeController::class, 'checkout']);
        Route::post('payments/stripe/verify-session', [StripeController::class, 'verifySession']);

        // Subscriptions
        Route::post('subscriptions/upgrade', [\App\Http\Controllers\API\V1\Payment\SubscriptionController::class, 'upgrade']);

        // Chat Routes
        Route::prefix('chat')->group(function () {
            Route::get('/', [ChatController::class, 'index']);
            Route::post('/', [ChatController::class, 'store']);
            Route::post('read', [ChatController::class, 'markAsRead']);
            Route::post('typing', [ChatController::class, 'typing']);
            // Personalized category ordering based on user's conversation history
            Route::get('categories/personalized', [\App\Http\Controllers\API\V1\Chat\CategoryController::class, 'personalized']);
        });

        // Clinical Advisor Routes
        Route::middleware(['clinical_advisor'])->prefix('clinical-advisor')->group(function () {
            Route::get('dashboard', [SessionReviewController::class, 'dashboard']);
            Route::get('reviews', [SessionReviewController::class, 'index']);
            Route::get('reviews/{id}', [SessionReviewController::class, 'show']);
            Route::post('reviews/{id}/approve', [SessionReviewController::class, 'approve']);
            Route::post('reviews/{id}/flag', [SessionReviewController::class, 'flag']);
            Route::post('reviews/{id}/escalate', [SessionReviewController::class, 'escalate']);
            Route::post('therapists/{id}/meeting-invite', [CommunicationController::class, 'sendMeetingInvite']);

            // Distress queue endpoints
            Route::get('distress-queue', [SessionReviewController::class, 'distressQueue']);
            Route::patch('distress-queue/{id}/resolve', [SessionReviewController::class, 'resolveDistressQueueItem']);

            // AI session audit (read + review)
            Route::get('session-audits',               [SessionAuditController::class, 'index']);
            Route::get('sessions/{uuid}/audit',        [SessionAuditController::class, 'show']);
            Route::post('sessions/{uuid}/audit/review',[SessionAuditController::class, 'review']);
        });

        // Product Manager routes
        Route::prefix('product-manager')->middleware(['role:product_manager|vp_product|product'])->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\API\V1\ProductManager\DashboardController::class, 'index']);
            Route::get('stats', [\App\Http\Controllers\API\V1\ProductManager\DashboardController::class, 'stats']);
            Route::get('tasks', [\App\Http\Controllers\API\V1\ProductManager\DashboardController::class, 'tasks']);
            Route::get('velocity', [\App\Http\Controllers\API\V1\ProductManager\DashboardController::class, 'velocity']);

            Route::apiResource('roadmap', \App\Http\Controllers\API\V1\ProductManager\RoadmapController::class);
            Route::apiResource('features', \App\Http\Controllers\API\V1\ProductManager\FeatureController::class);
            Route::apiResource('maintenance', ProductManagerMaintenanceController::class, ['as' => 'pm']);

            // Settings & Plans
            Route::get('settings/features', [\App\Http\Controllers\API\V1\ProductManager\SettingsController::class, 'features']);
            Route::put('settings/features', [\App\Http\Controllers\API\V1\ProductManager\SettingsController::class, 'updateFeatures']);
            Route::apiResource('plans', SubscriptionPlanController::class, ['as' => 'pm']);

            Route::get('team', [\App\Http\Controllers\API\V1\ProductManager\TeamController::class, 'index']);
            Route::get('team/{id}', [\App\Http\Controllers\API\V1\ProductManager\TeamController::class, 'show']);

            Route::get('analytics/metrics', [\App\Http\Controllers\API\V1\ProductManager\AnalyticsController::class, 'metrics']);
            Route::get('analytics/velocity', [\App\Http\Controllers\API\V1\ProductManager\AnalyticsController::class, 'velocity']);

            Route::get('reports', [\App\Http\Controllers\API\V1\ProductManager\ReportController::class, 'index']);
            Route::get('reports/{reportId}', [\App\Http\Controllers\API\V1\ProductManager\ReportController::class, 'generate']);
        });

        // Tech Team routes
        Route::prefix('tech')->middleware(['role:tech_team|tech'])->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\API\V1\Tech\DashboardController::class, 'index']);
            Route::get('stats', [\App\Http\Controllers\API\V1\Tech\DashboardController::class, 'index']);
            Route::get('health', [\App\Http\Controllers\API\V1\Tech\SystemHealthController::class, 'index']);
            Route::get('system-health', [\App\Http\Controllers\API\V1\Tech\SystemHealthController::class, 'index']);
            Route::get('system-status', [\App\Http\Controllers\API\V1\Tech\DashboardController::class, 'index']);
            Route::get('incidents', [\App\Http\Controllers\API\V1\Tech\LogController::class, 'index']);
            Route::get('logs', [\App\Http\Controllers\API\V1\Tech\LogController::class, 'index']);
            Route::get('system-logs', [\App\Http\Controllers\API\V1\Tech\LogController::class, 'systemLogs']);
            Route::apiResource('deployments', \App\Http\Controllers\API\V1\Tech\DeploymentController::class);
            Route::apiResource('maintenance', TechMaintenanceController::class, ['as' => 'tech']);
        });

        // CEO routes
        Route::prefix('ceo')->middleware(['role:ceo|admin'])->group(function () {
            Route::get('activity', [\App\Http\Controllers\API\V1\CEO\ActivityController::class, 'index']);
            Route::get('system-health', [\App\Http\Controllers\API\V1\Tech\SystemHealthController::class, 'index']);
            Route::get('logs',    [\App\Http\Controllers\API\V1\Tech\LogViewerController::class, 'index']);
            Route::delete('logs', [\App\Http\Controllers\API\V1\Tech\LogViewerController::class, 'clear']);
        });

        // Compliance routes
        Route::prefix('compliance')->middleware(['role:compliance'])->group(function () {
            Route::get('stats', [\App\Http\Controllers\API\V1\Compliance\DashboardController::class, 'stats']);
            Route::get('issues', [\App\Http\Controllers\API\V1\Compliance\DashboardController::class, 'issues']);
            Route::put('issues/{id}', [\App\Http\Controllers\API\V1\Compliance\DashboardController::class, 'updateIssue']);
            Route::get('audit', [\App\Http\Controllers\API\V1\Compliance\DashboardController::class, 'audit']);
            // NDPR 2023 checklist
            Route::get('ndpr', [\App\Http\Controllers\API\V1\Compliance\NdprController::class, 'index']);
            Route::patch('ndpr/{id}', [\App\Http\Controllers\API\V1\Compliance\NdprController::class, 'update']);
        });

        // Legal Advisor routes
        Route::prefix('legal')->middleware(['role:legal_advisor'])->group(function () {
            Route::get('stats', [\App\Http\Controllers\API\V1\Legal\DashboardController::class, 'stats']);
            Route::get('cases', [\App\Http\Controllers\API\V1\Legal\DashboardController::class, 'cases']);
            Route::get('cases/{id}', [\App\Http\Controllers\API\V1\Legal\DashboardController::class, 'showCase']);
        });

        // Partner routes
        Route::prefix('partner')->middleware(['role:partner'])->group(function () {
            Route::get('stats', [\App\Http\Controllers\API\V1\Partner\DashboardController::class, 'stats']);
            Route::get('employees', [\App\Http\Controllers\API\V1\Partner\DashboardController::class, 'employees']);
            Route::get('financial-flow', [\App\Http\Controllers\API\V1\Partner\DashboardController::class, 'financialFlow']);
            Route::post('leave-organization', \App\Http\Controllers\API\V1\Partner\LeaveOrganizationController::class);
        });

        // Health Personnel routes
        Route::prefix('health')->middleware(['role:health_personnel'])->group(function () {
            // Personal dashboard endpoints (scoped to authenticated user)
            Route::get('dashboard', [\App\Http\Controllers\API\V1\Health\DashboardController::class, 'dashboard']);
            Route::get('checkins/my', [\App\Http\Controllers\API\V1\Health\DashboardController::class, 'myCheckIns']);
            Route::get('reports/my', [\App\Http\Controllers\API\V1\Health\DashboardController::class, 'myReports']);
            Route::get('chart-data', [\App\Http\Controllers\API\V1\Health\DashboardController::class, 'chartData']);
            // Legacy endpoints kept for backward compatibility
            Route::get('stats', [\App\Http\Controllers\API\V1\Health\DashboardController::class, 'stats']);
            Route::get('check-ins', [\App\Http\Controllers\API\V1\Health\DashboardController::class, 'checkIns']);
            // Notifications (shared controller, scoped by auth user_id)
            Route::get('notifications/unread-count', [PatientNotificationController::class, 'unreadCount']);
            Route::get('notifications', [PatientNotificationController::class, 'index']);
            Route::patch('notifications/read-all', [PatientNotificationController::class, 'markAsRead']);
            Route::patch('notifications/{id}/read', [PatientNotificationController::class, 'markAsRead']);
            Route::delete('notifications/{id}', [PatientNotificationController::class, 'destroy']);
            Route::get('documents', [\App\Http\Controllers\API\V1\Health\DashboardController::class, 'documents']);
        });
    });

        // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Config endpoints (public-within-auth) ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
        Route::prefix('config')->group(function () {
            // Exchange rate: NGN/USD ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â cached 24h on backend via frankfurter.app
            Route::get('exchange-rate', [\App\Http\Controllers\API\V1\Config\ExchangeRateController::class, 'index']);
        });

        // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ KPI Snapshot (batched, 1h cache) ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
        // Returns all KPIs for a role in a single request to avoid N+1 calls.
        Route::get('kpi/snapshot', \App\Http\Controllers\API\V1\Kpi\SnapshotController::class);

        // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ President Portal ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
        Route::prefix('president')->middleware(['role:president|super_admin|founder'])->group(function () {
            Route::get('overview', [\App\Http\Controllers\API\V1\President\DashboardController::class, 'overview']);
        });

        // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ CFO / Finance extended overview ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
        // Note: /finance/* routes already exist; this adds the CFO-specific aggregated view.
        Route::prefix('finance')->middleware(['role:cfo|finance|admin|super_admin'])->group(function () {
            Route::get('overview', [\App\Http\Controllers\API\V1\Cfo\DashboardController::class, 'overview']);
        });

        // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Audit Portal ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
        Route::prefix('audit')->middleware(['role:audit|admin|super_admin'])->group(function () {
            Route::get('overview', [\App\Http\Controllers\API\V1\Audit\DashboardController::class, 'overview']);

            // Paginated, filterable audit event log
            // Query params: search, category, severity, from (date), to (date), page, per_page
            Route::get('log', function (\Illuminate\Http\Request $request) {
                $perPage = min((int) $request->get('per_page', 25), 100);
                $page    = max((int) $request->get('page', 1), 1);
                $tableExists = \Schema::hasTable('audit_logs');

                if (! $tableExists) {
                    // Fallback: serve admin_logs as the audit event source
                    $query = \DB::table('admin_logs')
                        ->leftJoin('users', 'admin_logs.user_id', '=', 'users.id')
                        ->select(
                            'admin_logs.id',
                            'admin_logs.created_at as timestamp',
                            'admin_logs.user_id',
                            \DB::raw("CONCAT(COALESCE(users.first_name,''), ' ', COALESCE(users.last_name,'')) as user_name"),
                            'users.email as user_email',
                            'admin_logs.action',
                            'admin_logs.target_type as resource',
                            'admin_logs.target_id as resource_id',
                            'admin_logs.ip_address',
                            'admin_logs.user_agent',
                            \DB::raw("'low' as severity"),
                            \DB::raw("'admin' as category"),
                            \DB::raw("'success' as status"),
                            \DB::raw("NULL as details")
                        );

                    if ($request->filled('search')) {
                        $s = '%'.$request->search.'%';
                        $query->where(function ($q) use ($s) {
                            $q->where('admin_logs.action', 'like', $s)
                              ->orWhere('users.first_name', 'like', $s)
                              ->orWhere('users.last_name', 'like', $s)
                              ->orWhere('admin_logs.ip_address', 'like', $s);
                        });
                    }
                    if ($request->filled('from')) { $query->whereDate('admin_logs.created_at', '>=', $request->from); }
                    if ($request->filled('to'))   { $query->whereDate('admin_logs.created_at', '<=', $request->to);   }

                    $result = $query->orderByDesc('admin_logs.created_at')->paginate($perPage, ['*'], 'page', $page);
                    return response()->json(['success' => true, 'message' => 'Audit log retrieved.', 'data' => $result->items(), 'meta' => ['current_page' => $result->currentPage(), 'last_page' => $result->lastPage(), 'per_page' => $result->perPage(), 'total' => $result->total(), 'from' => $result->firstItem(), 'to' => $result->lastItem()]]);
                }

                $query = \DB::table('audit_logs')
                    ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
                    ->select(
                        'audit_logs.id',
                        'audit_logs.created_at as timestamp',
                        'audit_logs.user_id',
                        \DB::raw("CONCAT(COALESCE(users.first_name,''), ' ', COALESCE(users.last_name,'')) as user_name"),
                        'users.email as user_email',
                        'audit_logs.action',
                        'audit_logs.resource',
                        'audit_logs.resource_id',
                        'audit_logs.ip_address',
                        'audit_logs.user_agent',
                        'audit_logs.severity',
                        'audit_logs.category',
                        'audit_logs.status',
                        'audit_logs.details'
                    );

                if ($request->filled('search')) {
                    $s = '%'.$request->search.'%';
                    $query->where(function ($q) use ($s) {
                        $q->where('audit_logs.action', 'like', $s)
                          ->orWhere('users.first_name', 'like', $s)
                          ->orWhere('users.last_name', 'like', $s)
                          ->orWhere('audit_logs.ip_address', 'like', $s)
                          ->orWhere('audit_logs.resource', 'like', $s);
                    });
                }
                if ($request->filled('category') && $request->category !== 'all') {
                    $query->where('audit_logs.category', $request->category);
                }
                if ($request->filled('severity') && $request->severity !== 'all') {
                    $query->where('audit_logs.severity', $request->severity);
                }
                if ($request->filled('from')) { $query->whereDate('audit_logs.created_at', '>=', $request->from); }
                if ($request->filled('to'))   { $query->whereDate('audit_logs.created_at', '<=', $request->to);   }

                $result = $query->orderByDesc('audit_logs.created_at')->paginate($perPage, ['*'], 'page', $page);

                return response()->json([
                    'success' => true,
                    'message' => 'Audit log retrieved.',
                    'data'    => $result->items(),
                    'meta'    => [
                        'current_page' => $result->currentPage(),
                        'last_page'    => $result->lastPage(),
                        'per_page'     => $result->perPage(),
                        'total'        => $result->total(),
                        'from'         => $result->firstItem(),
                        'to'           => $result->lastItem(),
                    ],
                ]);
            });
        });

        // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ HR: Departments & Designations & Employee Records ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
        Route::prefix('hr')->group(function () {
            // Departments (HR / admin manage; all staff can read)
            Route::get('departments', [\App\Http\Controllers\API\V1\HR\DepartmentController::class, 'index']);
            Route::post('departments', [\App\Http\Controllers\API\V1\HR\DepartmentController::class, 'store'])
                 ->middleware(['role:hr|admin|super_admin']);
            Route::get('departments/{id}', [\App\Http\Controllers\API\V1\HR\DepartmentController::class, 'show']);
            Route::put('departments/{id}', [\App\Http\Controllers\API\V1\HR\DepartmentController::class, 'update'])
                 ->middleware(['role:hr|admin|super_admin']);
            Route::delete('departments/{id}', [\App\Http\Controllers\API\V1\HR\DepartmentController::class, 'destroy'])
                 ->middleware(['role:hr|admin|super_admin']);

            // Designations
            Route::get('designations', [\App\Http\Controllers\API\V1\HR\DesignationController::class, 'index']);
            Route::post('designations', [\App\Http\Controllers\API\V1\HR\DesignationController::class, 'store'])
                 ->middleware(['role:hr|admin|super_admin']);
            Route::get('designations/{id}', [\App\Http\Controllers\API\V1\HR\DesignationController::class, 'show']);
            Route::put('designations/{id}', [\App\Http\Controllers\API\V1\HR\DesignationController::class, 'update'])
                 ->middleware(['role:hr|admin|super_admin']);
            Route::delete('designations/{id}', [\App\Http\Controllers\API\V1\HR\DesignationController::class, 'destroy'])
                 ->middleware(['role:hr|admin|super_admin']);

            // Employee Records
            Route::get('employee-records', [\App\Http\Controllers\API\V1\HR\EmployeeRecordController::class, 'index'])
                 ->middleware(['role:hr|admin|super_admin|coo|ceo|president']);
            Route::post('employee-records', [\App\Http\Controllers\API\V1\HR\EmployeeRecordController::class, 'store'])
                 ->middleware(['role:hr|admin|super_admin']);
            Route::get('employee-records/{id}', [\App\Http\Controllers\API\V1\HR\EmployeeRecordController::class, 'show'])
                 ->middleware(['role:hr|admin|super_admin|coo|ceo|president']);
            Route::put('employee-records/{id}', [\App\Http\Controllers\API\V1\HR\EmployeeRecordController::class, 'update'])
                 ->middleware(['role:hr|admin|super_admin']);
            Route::delete('employee-records/{id}', [\App\Http\Controllers\API\V1\HR\EmployeeRecordController::class, 'destroy'])
                 ->middleware(['role:hr|admin|super_admin']);
            // Archived records ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â view and restore (admin/ceo only)
            Route::get('employee-records/archived', [\App\Http\Controllers\API\V1\HR\EmployeeRecordController::class, 'archived'])
                 ->middleware(['role:admin|super_admin|ceo|president']);
            Route::post('employee-records/{id}/restore', [\App\Http\Controllers\API\V1\HR\EmployeeRecordController::class, 'restore'])
                 ->middleware(['role:admin|super_admin|ceo|president']);

            // Org chart (visible to executives + HR)
            Route::get('org-chart', [\App\Http\Controllers\API\V1\HR\EmployeeRecordController::class, 'orgChart'])
                 ->middleware(['role:hr|admin|super_admin|coo|ceo|cfo|president|cgo|audit']);
        });

        // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Approval Engine ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
        // Every authenticated user can view their own requests and inbox.
        // Approve/reject/review is gated inside the engine by step ownership.
        Route::prefix('approvals')->group(function () {
            Route::get('inbox', [\App\Http\Controllers\API\V1\Approval\ApprovalController::class, 'inbox']);
            Route::get('/', [\App\Http\Controllers\API\V1\Approval\ApprovalController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\API\V1\Approval\ApprovalController::class, 'store']);
            Route::get('{uuid}', [\App\Http\Controllers\API\V1\Approval\ApprovalController::class, 'show']);
            Route::post('{uuid}/approve', [\App\Http\Controllers\API\V1\Approval\ApprovalController::class, 'approve']);
            Route::post('{uuid}/reject', [\App\Http\Controllers\API\V1\Approval\ApprovalController::class, 'reject']);
            Route::post('{uuid}/review', [\App\Http\Controllers\API\V1\Approval\ApprovalController::class, 'requestReview']);
            Route::post('{uuid}/respond', [\App\Http\Controllers\API\V1\Approval\ApprovalController::class, 'respond']);
            Route::post('{uuid}/cancel', [\App\Http\Controllers\API\V1\Approval\ApprovalController::class, 'cancel']);
        });

        // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Budget Approval Workflow ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
        // POST   /budgets           ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â submit new budget (marketing|sales|coo)
        // GET    /budgets           ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â list (own dept, or all for approvers)
        // GET    /budgets/{id}      ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â show detail with approval trail
        // PUT    /budgets/{id}      ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â update draft before submission
        // DELETE /budgets/{id}      ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â delete draft
        // POST   /budgets/{id}/submit            ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â submit for COO approval
        // POST   /budgets/{id}/approve/coo       ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â COO approves ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ CEO queue
        // POST   /budgets/{id}/approve/ceo       ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â CEO approves ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ Finance queue
        // POST   /budgets/{id}/approve/finance   ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Finance finalises ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ approved
        // POST   /budgets/{id}/reject            ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â any approver rejects
        // POST   /budgets/{id}/query/ceo         ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â CEO queries back to creator
        // POST   /budgets/{id}/respond           ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â creator responds to CEO query
        Route::prefix('budgets')->group(function () {
            Route::get('/', [\App\Http\Controllers\API\V1\Budget\BudgetController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\API\V1\Budget\BudgetController::class, 'store']);
            Route::get('{id}', [\App\Http\Controllers\API\V1\Budget\BudgetController::class, 'show']);
            Route::put('{id}', [\App\Http\Controllers\API\V1\Budget\BudgetController::class, 'update']);
            Route::delete('{id}', [\App\Http\Controllers\API\V1\Budget\BudgetController::class, 'destroy']);
            Route::post('{id}/submit', [\App\Http\Controllers\API\V1\Budget\BudgetController::class, 'submit']);
            Route::post('{id}/approve/coo', [\App\Http\Controllers\API\V1\Budget\BudgetController::class, 'approveCoo']);
            Route::post('{id}/approve/ceo', [\App\Http\Controllers\API\V1\Budget\BudgetController::class, 'approveCeo']);
            Route::post('{id}/approve/finance', [\App\Http\Controllers\API\V1\Budget\BudgetController::class, 'approveFinance']);
            Route::post('{id}/reject', [\App\Http\Controllers\API\V1\Budget\BudgetController::class, 'reject']);
            Route::post('{id}/query/ceo', [\App\Http\Controllers\API\V1\Budget\BudgetController::class, 'queryCeo']);
            Route::post('{id}/respond', [\App\Http\Controllers\API\V1\Budget\BudgetController::class, 'respondToQuery']);
        });

        // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Campaign Expenses / Proof of Payment ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
        // POST   /campaign-expenses           ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â submit expense + upload proof
        // GET    /campaign-expenses           ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â list (own dept)
        // GET    /campaign-expenses/{id}      ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â detail
        // PUT    /campaign-expenses/{id}      ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â edit before review
        // DELETE /campaign-expenses/{id}      ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â remove
        // POST   /campaign-expenses/{id}/review ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â marketing manager / finance reviews
        Route::prefix('campaign-expenses')->group(function () {
            Route::get('/', [\App\Http\Controllers\API\V1\Marketing\CampaignExpenseController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\API\V1\Marketing\CampaignExpenseController::class, 'store']);
            Route::get('{id}', [\App\Http\Controllers\API\V1\Marketing\CampaignExpenseController::class, 'show']);
            Route::put('{id}', [\App\Http\Controllers\API\V1\Marketing\CampaignExpenseController::class, 'update']);
            Route::delete('{id}', [\App\Http\Controllers\API\V1\Marketing\CampaignExpenseController::class, 'destroy']);
            Route::post('{id}/review', [\App\Http\Controllers\API\V1\Marketing\CampaignExpenseController::class, 'review']);
        });

        // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Page View Audit Trail ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
        // POST /page-views           ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â record a view (called from dashboard pages)
        // GET  /page-views?page_key= ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â who viewed a specific page (admin/audit only)
        Route::prefix('page-views')->group(function () {
            Route::post('/', [\App\Http\Controllers\API\V1\Audit\PageViewController::class, 'store']);
            Route::get('/', [\App\Http\Controllers\API\V1\Audit\PageViewController::class, 'index'])
                ->middleware(['role:audit|admin|super_admin|cfo|coo|ceo|president']);
        });

        // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ OKR / KPI System ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
        // Access: executives (ceo|coo|cgo|admin) + all department leads + contributors
        // Auth check is handled inside OkrController per-method for fine-grained control.
        Route::prefix('okr')->group(function () {
            // Read
            Route::get('objectives',        [\App\Http\Controllers\API\V1\Okr\OkrController::class, 'objectives']);
            Route::get('company-health',    [\App\Http\Controllers\API\V1\Okr\OkrController::class, 'companyHealth']);
            Route::get('key-results/{id}',  [\App\Http\Controllers\API\V1\Okr\OkrController::class, 'showKeyResult']);
            Route::get('bindable-metrics',  [\App\Http\Controllers\API\V1\Okr\OkrController::class, 'bindableMetrics']);
            Route::get('team-members',      [\App\Http\Controllers\API\V1\Okr\OkrController::class, 'teamMembers']);

            // Objectives
            Route::post('objectives',               [\App\Http\Controllers\API\V1\Okr\OkrController::class, 'storeObjective']);
            Route::put('objectives/{id}',           [\App\Http\Controllers\API\V1\Okr\OkrController::class, 'updateObjective']);
            Route::delete('objectives/{id}',        [\App\Http\Controllers\API\V1\Okr\OkrController::class, 'destroyObjective']);

            // Key Results
            Route::post('key-results',              [\App\Http\Controllers\API\V1\Okr\OkrController::class, 'storeKeyResult']);
            Route::put('key-results/{id}',          [\App\Http\Controllers\API\V1\Okr\OkrController::class, 'updateKeyResult']);
            Route::delete('key-results/{id}',       [\App\Http\Controllers\API\V1\Okr\OkrController::class, 'destroyKeyResult']);
            Route::post('key-results/{id}/check-in',[\App\Http\Controllers\API\V1\Okr\OkrController::class, 'checkIn']);

            // Initiatives
            Route::post('initiatives',              [\App\Http\Controllers\API\V1\Okr\OkrController::class, 'storeInitiative']);
            Route::put('initiatives/{id}',          [\App\Http\Controllers\API\V1\Okr\OkrController::class, 'updateInitiative']);
            Route::delete('initiatives/{id}',       [\App\Http\Controllers\API\V1\Okr\OkrController::class, 'destroyInitiative']);
        });

    // Include Dashboard Routes
    require __DIR__.'/api/dashboard.php';
});

