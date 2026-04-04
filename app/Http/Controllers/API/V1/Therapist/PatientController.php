<?php

namespace App\Http\Controllers\API\V1\Therapist;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\TherapistPatientResource;
use App\Models\JournalEntry;
use App\Models\MoodLog;
use App\Models\OnwyndScoreLog;
use App\Models\SleepLog;
use App\Models\StressAssessment;
use App\Models\User;
use App\Repositories\Contracts\TherapyRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends BaseController
{
    protected $therapyRepository;

    public function __construct(TherapyRepositoryInterface $therapyRepository)
    {
        $this->therapyRepository = $therapyRepository;
    }

    public function index(Request $request): JsonResponse
    {
        // Get patients who have had a session with this therapist
        $patientIds = $this->therapyRepository->getPatientIds($request->user()->id);

        $patients = User::whereIn('id', $patientIds)
            ->with(['patient', 'profile'])
            ->paginate(15);

        return $this->sendResponse(TherapistPatientResource::collection($patients), 'Patients retrieved successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $therapistId = (int) ($request->user()?->id ?? 0);
        $hasAccess = $this->therapyRepository->hasRelationship($therapistId, $id);
        if (! $hasAccess) {
            return $this->sendError('You do not have permission to view this patient details.', [], 403);
        }
        $patient = User::with(['patient', 'profile'])->find($id);
        if (! $patient) {
            return $this->sendError('Patient not found.');
        }
        $preferences = $patient->patient ? ($patient->patient->preferences ?? []) : [];
        $shareProgress = data_get($preferences, 'share_progress', false);
        $visibility = data_get($preferences, 'profile_visibility', 'private');
        if (! $shareProgress && $visibility !== 'therapists') {
            return $this->sendError('Patient has not granted access to progress.', [], 403);
        }

        $sessions = $this->therapyRepository->getSharedSessions($therapistId, $id);

        $latestScore = OnwyndScoreLog::where('user_id', $id)->latest('logged_at')->first();
        $avgMood = MoodLog::where('user_id', $id)->where('created_at', '>=', now()->subDays(7))->avg('mood_score');
        $avgSleep = SleepLog::where('user_id', $id)->where('created_at', '>=', now()->subDays(7))->avg('duration_minutes');
        $currentStress = StressAssessment::where('user_id', $id)->latest()->first();

        $data = (new TherapistPatientResource($patient))->resolve(request());
        $data['sessions_history'] = $sessions;
        $data['health_overview'] = [
            'latest_score' => $latestScore ? $latestScore->score : null,
            'avg_mood_7_days' => round($avgMood, 1),
            'avg_sleep_7_days' => $avgSleep ? round($avgSleep / 60, 1).' hrs' : null,
            'current_stress' => $currentStress ? $currentStress->stress_level : null,
        ];

        return $this->sendResponse($data, 'Patient details retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'nullable|string|min:8',
            'department' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,inactive,monitoring,critical',
        ]);

        try {
            $therapistId = $request->user()->id;
            
            // Create user
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => $validated['password'] ? bcrypt($validated['password']) : bcrypt('password'),
                'role_id' => 3, // Assuming 3 is patient role
                'is_active' => true,
            ]);

            // Create patient record
            $user->patient()->create([
                'department' => $validated['department'] ?? 'General',
                'status' => $validated['status'] ?? 'active',
            ]);

            // Associate with therapist via therapy session or relationship
            // This depends on your business logic - you may need to create a session or relationship

            return $this->sendResponse($user->load('patient'), 'Patient created successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to create patient: ' . $e->getMessage(), [], 400);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $therapistId = (int) ($request->user()?->id ?? 0);
        $user = User::with('patient')->find($id);
        
        if (! $user) {
            return $this->sendError('Patient not found.');
        }

        // Verify therapist has access
        if (! $this->therapyRepository->hasRelationship($therapistId, $id)) {
            return $this->sendError('You do not have permission to update this patient.', [], 403);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'department' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,inactive,monitoring,critical',
        ]);

        try {
            // Update user fields
            if (isset($validated['first_name'])) {
                $user->first_name = $validated['first_name'];
            }
            if (isset($validated['last_name'])) {
                $user->last_name = $validated['last_name'];
            }
            if (isset($validated['email'])) {
                $user->email = $validated['email'];
            }
            $user->save();

            // Update patient record
            if ($user->patient) {
                $user->patient->update([
                    'department' => $validated['department'] ?? $user->patient->department,
                    'status' => $validated['status'] ?? $user->patient->status,
                ]);
            }

            return $this->sendResponse($user->load('patient'), 'Patient updated successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to update patient: ' . $e->getMessage(), [], 400);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $therapistId = (int) ($request->user()?->id ?? 0);
        $user = User::with('patient')->find($id);
        
        if (! $user) {
            return $this->sendError('Patient not found.');
        }

        // Verify therapist has access
        if (! $this->therapyRepository->hasRelationship($therapistId, $id)) {
            return $this->sendError('You do not have permission to delete this patient.', [], 403);
        }

        try {
            // Soft delete user
            $user->delete();

            return $this->sendResponse(null, 'Patient deleted successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete patient: ' . $e->getMessage(), [], 400);
        }
    }

    public function importPatients(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        try {
            $file = $request->file('file');
            $handle = fopen($file->getRealPath(), 'r');
            $header = fgetcsv($handle);
            
            $imported = 0;
            $errors = [];

            while (($row = fgetcsv($handle)) !== false) {
                try {
                    $data = array_combine($header, $row);
                    
                    // Create user
                    $user = User::create([
                        'first_name' => $data['first_name'] ?? '',
                        'last_name' => $data['last_name'] ?? '',
                        'email' => $data['email'] ?? '',
                        'password' => bcrypt('password'),
                        'role_id' => 3, // Patient role
                        'is_active' => true,
                    ]);

                    // Create patient record
                    $user->patient()->create([
                        'department' => $data['department'] ?? 'General',
                        'status' => $data['status'] ?? 'active',
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = 'Row ' . ($imported + 1) . ': ' . $e->getMessage();
                }
            }

            fclose($handle);

            return $this->sendResponse([
                'imported' => $imported,
                'errors' => $errors,
            ], 'Patients imported successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to import patients: ' . $e->getMessage(), [], 400);
        }
    }

    public function getHealthData(Request $request, int $id, string $type): JsonResponse
    {
        $therapistId = (int) ($request->user()?->id ?? 0);
        if (! $this->therapyRepository->hasRelationship($therapistId, $id)) {
            return $this->sendError('You do not have permission to view this patient details.', [], 403);
        }
        $patient = User::with('patient')->find($id);
        if (! $patient) {
            return $this->sendError('Patient not found.');
        }
        $preferences = $patient->patient ? ($patient->patient->preferences ?? []) : [];
        $shareProgress = data_get($preferences, 'share_progress', false);
        $visibility = data_get($preferences, 'profile_visibility', 'private');
        if (! $shareProgress && $visibility !== 'therapists') {
            return $this->sendError('Patient has not granted access to progress.', [], 403);
        }

        switch ($type) {
            case 'sleep':
                $data = SleepLog::where('user_id', $id)->latest()->paginate(20);
                break;
            case 'mood':
                $data = MoodLog::where('user_id', $id)->latest()->paginate(20);
                break;
            case 'stress':
                $data = StressAssessment::where('user_id', $id)->latest()->paginate(20);
                break;
            case 'journal':
                // Only show non-private entries or if user consented (Assuming all for now for therapist)
                $data = JournalEntry::where('user_id', $id)->latest()->paginate(20);
                break;
            case 'score':
                $data = OnwyndScoreLog::where('user_id', $id)->latest()->paginate(20);
                break;
            default:
                return $this->sendError('Invalid health data type.');
        }

        return $this->sendResponse($data, ucfirst($type).' logs retrieved successfully.');
    }
}
