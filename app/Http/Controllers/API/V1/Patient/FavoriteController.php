<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;

class FavoriteController extends BaseController
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user->patient) {
            return $this->sendError('Patient profile not found.', [], 404);
        }

        $favorites = $user->patient->favorites()
            ->with('user:id,first_name,last_name,profile_photo')
            ->paginate($request->get('per_page', 20));

        return $this->sendResponse($favorites, 'Favorite therapists retrieved successfully.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'therapist_id' => 'required|exists:therapist_profiles,id',
        ]);

        $user = $request->user();
        if (! $user->patient) {
            return $this->sendError('Patient profile not found.', [], 404);
        }

        $therapistId = $request->therapist_id;

        if ($user->patient->favorites()->where('therapist_id', $therapistId)->exists()) {
            return $this->sendError('Therapist is already in favorites.', [], 409);
        }

        $user->patient->favorites()->attach($therapistId);

        return $this->sendResponse([], 'Therapist added to favorites.');
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if (! $user->patient) {
            return $this->sendError('Patient profile not found.', [], 404);
        }

        if (! $user->patient->favorites()->where('therapist_id', $id)->exists()) {
            return $this->sendError('Therapist not found in favorites.', [], 404);
        }

        $user->patient->favorites()->detach($id);

        return $this->sendResponse([], 'Therapist removed from favorites.');
    }
}
