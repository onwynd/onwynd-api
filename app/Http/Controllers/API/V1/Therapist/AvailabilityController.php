<?php

namespace App\Http\Controllers\API\V1\Therapist;

use App\Http\Controllers\API\BaseController;
use App\Models\TherapistAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AvailabilityController extends BaseController
{
    public function index(Request $request)
    {
        $availabilities = TherapistAvailability::where('therapist_id', $request->user()->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return $this->sendResponse($availabilities, 'Availability slots retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'day_of_week' => 'required|integer|between:0,6', // 0 = Sunday
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'is_recurring' => 'boolean',
            'specific_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $availability = TherapistAvailability::create([
            'therapist_id' => $request->user()->id,
            'day_of_week' => $request->day_of_week,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'is_recurring' => $request->boolean('is_recurring', true),
            'specific_date' => $request->specific_date,
            'is_available' => true,
        ]);

        return $this->sendResponse($availability, 'Availability slot created successfully.');
    }

    public function update(Request $request, $id)
    {
        $availability = TherapistAvailability::where('therapist_id', $request->user()->id)->find($id);

        if (! $availability) {
            return $this->sendError('Availability slot not found.');
        }

        $validator = Validator::make($request->all(), [
            'day_of_week' => 'sometimes|integer|between:0,6',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'is_recurring' => 'boolean',
            'specific_date' => 'nullable|date',
            'is_available' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $availability->update($request->all());

        return $this->sendResponse($availability, 'Availability slot updated successfully.');
    }

    public function destroy($id)
    {
        $availability = TherapistAvailability::where('therapist_id', auth()->id())->find($id);

        if (! $availability) {
            return $this->sendError('Availability slot not found.');
        }

        $availability->delete();

        return $this->sendResponse([], 'Availability slot deleted successfully.');
    }
}
