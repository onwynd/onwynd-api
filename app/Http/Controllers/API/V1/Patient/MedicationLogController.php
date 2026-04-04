<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\Controller;
use App\Models\MedicationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MedicationLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $logs = MedicationLog::where('user_id', Auth::id())
            ->when($request->date, function ($q) use ($request) {
                return $q->whereDate('taken_at', $request->date);
            })
            ->orderBy('taken_at', 'desc')
            ->paginate(20);

        return response()->json(['status' => 'success', 'data' => $logs]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'medication_name' => 'required|string',
            'prescription_id' => 'nullable|exists:prescriptions,id',
            'dosage_taken' => 'required|string',
            'taken_at' => 'required|date',
            'notes' => 'nullable|string',
            'mood_rating' => 'nullable|integer|min:1|max:5',
            'skipped' => 'boolean',
            'skip_reason' => 'nullable|string',
        ]);

        $log = MedicationLog::create(array_merge($validated, ['user_id' => Auth::id()]));

        return response()->json(['status' => 'success', 'data' => $log], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $log = MedicationLog::where('user_id', Auth::id())->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $log]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $log = MedicationLog::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'medication_name' => 'sometimes|string',
            'dosage_taken' => 'sometimes|string',
            'taken_at' => 'sometimes|date',
            'notes' => 'nullable|string',
            'mood_rating' => 'nullable|integer|min:1|max:5',
            'skipped' => 'boolean',
            'skip_reason' => 'nullable|string',
        ]);

        $log->update($validated);

        return response()->json(['status' => 'success', 'data' => $log]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $log = MedicationLog::where('user_id', Auth::id())->findOrFail($id);
        $log->delete();

        return response()->json(['status' => 'success', 'message' => 'Log deleted']);
    }
}
