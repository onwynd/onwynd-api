<?php

namespace App\Http\Controllers\API\V1\Medical;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PrescriptionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index()
    {
        $user = Auth::user();
        $user->load('role');

        if ($user->role && $user->role->slug === 'therapist') {
            $prescriptions = Prescription::where('doctor_id', $user->id)
                ->with(['patient', 'logs'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
        } else {
            $prescriptions = Prescription::where('patient_id', $user->id)
                ->with(['doctor', 'logs'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
        }

        return response()->json([
            'success' => true,
            'data' => $prescriptions,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $user->load('role');

        if (! $user->role || $user->role->slug !== 'therapist') {
            return response()->json([
                'success' => false,
                'message' => 'Only therapists can issue prescriptions.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:users,id',
            'medication_name' => 'required|string|max:255',
            'dosage' => 'required|string|max:255',
            'frequency' => 'required|string|max:255',
            'duration' => 'required|string|max:255',
            'instructions' => 'nullable|string',
            'digital_signature' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $prescription = Prescription::create([
            'patient_id' => $request->patient_id,
            'doctor_id' => $user->id,
            'medication_name' => $request->medication_name,
            'dosage' => $request->dosage,
            'frequency' => $request->frequency,
            'duration' => $request->duration,
            'instructions' => $request->instructions,
            'status' => 'active',
            'issued_at' => now(),
            'digital_signature' => $request->digital_signature,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prescription issued successfully.',
            'data' => $prescription,
        ], 201);
    }

    public function show($uuid)
    {
        $prescription = Prescription::where('uuid', $uuid)->with(['patient', 'doctor', 'logs'])->firstOrFail();

        $user = Auth::user();
        if ($user->id !== $prescription->patient_id && $user->id !== $prescription->doctor_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json(['success' => true, 'data' => $prescription]);
    }
}
