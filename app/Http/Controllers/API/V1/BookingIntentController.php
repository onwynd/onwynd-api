<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Models\BookingIntent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingIntentController extends BaseController
{
    /**
     * Record that the authenticated user has started a booking flow.
     * Upserts so refreshing the page doesn't create duplicate rows.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'context'        => 'nullable|string|max:50',
            'stage'          => 'nullable|string|in:page_view,therapist_selected,payment_initiated',
            'return_url'     => 'nullable|string|max:500',
            'therapist_id'   => 'nullable|integer|exists:users,id',
            'therapist_name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        // Upsert: if an open (incomplete) intent exists within the last hour, update it.
        $intent = BookingIntent::where('user_id', $user->id)
            ->whereNull('completed_at')
            ->where('created_at', '>=', now()->subHour())
            ->latest()
            ->first();

        // Stage priority — never downgrade (page_view < therapist_selected < payment_initiated)
        $stagePriority = ['page_view' => 0, 'therapist_selected' => 1, 'payment_initiated' => 2];
        $incomingStage = $request->input('stage', 'page_view');

        if ($intent) {
            $data = $request->only('context', 'return_url', 'therapist_id', 'therapist_name');
            $currentPriority = $stagePriority[$intent->stage] ?? 0;
            if (($stagePriority[$incomingStage] ?? 0) > $currentPriority) {
                $data['stage'] = $incomingStage;
            }
            $intent->update($data);
        } else {
            $intent = BookingIntent::create([
                'user_id'        => $user->id,
                'context'        => $request->input('context', 'general'),
                'stage'          => $incomingStage,
                'return_url'     => $request->input('return_url'),
                'therapist_id'   => $request->input('therapist_id'),
                'therapist_name' => $request->input('therapist_name'),
            ]);
        }

        return $this->sendResponse(['id' => (int) $intent->getKey()], 'Intent recorded');
    }

    /**
     * Mark the most recent open intent as completed (booking confirmed).
     */
    public function complete(Request $request): JsonResponse
    {
        BookingIntent::where('user_id', $request->user()->id)
            ->whereNull('completed_at')
            ->latest()
            ->first()
            ?->update(['completed_at' => now()]);

        return $this->sendResponse([], 'Intent completed');
    }
}
