<?php

namespace App\Http\Controllers\API\V1\Medical;

use App\Http\Controllers\Controller;
use App\Models\MedicationLog;
use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MedicationLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index()
    {
        $user = Auth::user();
        $logs = MedicationLog::where('user_id', $user->id)
            ->with('prescription')
            ->orderBy('taken_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $logs]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'prescription_id' => 'nullable|exists:prescriptions,id',
            'medication_name' => 'required_without:prescription_id|string|max:255',
            'dosage_taken' => 'required|string|max:255',
            'taken_at' => 'required|date',
            'skipped' => 'boolean',
            'skip_reason' => 'required_if:skipped,true|nullable|string',
            'mood_rating' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $medicationName = $request->medication_name;

        // Verify prescription belongs to user if provided
        if ($request->prescription_id) {
            $prescription = Prescription::find($request->prescription_id);
            if ($prescription->patient_id !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized prescription access'], 403);
            }
            if (empty($medicationName)) {
                $medicationName = $prescription->medication_name;
            }
        }

        $log = MedicationLog::create([
            'user_id' => $user->id,
            'prescription_id' => $request->prescription_id,
            'medication_name' => $medicationName,
            'dosage_taken' => $request->dosage_taken,
            'taken_at' => $request->taken_at,
            'skipped' => $request->boolean('skipped', false),
            'skip_reason' => $request->skip_reason,
            'mood_rating' => $request->mood_rating,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Medication log added.',
            'data' => $log,
        ], 201);
    }
}
