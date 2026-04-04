<?php

namespace App\Http\Controllers\API\V1\Secretary;

use App\Http\Controllers\API\BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class PatientController extends BaseController
{
    /**
     * Display a listing of patients.
     */
    public function index(Request $request)
    {
        $query = User::role('patient');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $patients = $query->orderBy('created_at', 'desc')->paginate(15);

        // Transform for frontend
        $data = $patients->getCollection()->transform(function ($patient) {
            return [
                'id' => $patient->id,
                'name' => $patient->name,
                'email' => $patient->email,
                'phone' => $patient->phone_number ?? 'N/A',
                'status' => $patient->email_verified_at ? 'Active' : 'Pending',
                'joinedDate' => $patient->created_at->format('Y-m-d'),
                'avatar' => $patient->profile_photo_url,
            ];
        });

        $patients->setCollection($data);

        return $this->sendResponse($patients, 'Patients retrieved successfully.');
    }

    /**
     * Store a newly created patient in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone_number' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
        ]);

        $patient = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
        ]);

        $patient->assignRole('patient');

        return $this->sendResponse($patient, 'Patient created successfully.');
    }

    /**
     * Display the specified patient.
     */
    public function show($id)
    {
        $patient = User::role('patient')->find($id);

        if (! $patient) {
            return $this->sendError('Patient not found.');
        }

        $data = [
            'id' => $patient->id,
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'email' => $patient->email,
            'phone' => $patient->phone_number,
            'status' => $patient->email_verified_at ? 'Active' : 'Pending',
            'joinedDate' => $patient->created_at->format('Y-m-d'),
            'avatar' => $patient->profile_photo_url,
            // Add more details if needed
        ];

        return $this->sendResponse($data, 'Patient details retrieved successfully.');
    }

    /**
     * Update the specified patient in storage.
     */
    public function update(Request $request, $id)
    {
        $patient = User::role('patient')->find($id);

        if (! $patient) {
            return $this->sendError('Patient not found.');
        }

        $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($patient->id)],
            'phone_number' => 'nullable|string|max:20',
        ]);

        $patient->update($request->only(['first_name', 'last_name', 'email', 'phone_number']));

        return $this->sendResponse($patient, 'Patient updated successfully.');
    }
}
