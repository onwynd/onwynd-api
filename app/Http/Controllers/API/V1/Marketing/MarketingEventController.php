<?php

namespace App\Http\Controllers\API\V1\Marketing;

use App\Http\Controllers\API\BaseController;
use App\Models\MarketingEvent;
use Illuminate\Http\Request;

class MarketingEventController extends BaseController
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('admin')) {
            return $this->sendError('Forbidden', [], 403);
        }
        $items = MarketingEvent::orderBy('event_date')->get();

        return $this->sendResponse($items, 'Events retrieved.');
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('admin')) {
            return $this->sendError('Forbidden', [], 403);
        }
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'event_date' => 'required|date',
            'audience' => 'nullable|array',
            'description' => 'nullable|string',
            'template_html' => 'nullable|string',
            'active' => 'boolean',
        ]);
        $item = MarketingEvent::create($data);

        return $this->sendResponse($item, 'Event created.');
    }

    public function update(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('admin')) {
            return $this->sendError('Forbidden', [], 403);
        }
        $item = MarketingEvent::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:191',
            'event_date' => 'sometimes|required|date',
            'audience' => 'nullable|array',
            'description' => 'nullable|string',
            'template_html' => 'nullable|string',
            'active' => 'boolean',
        ]);
        $item->update($data);

        return $this->sendResponse($item, 'Event updated.');
    }

    public function destroy(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('admin')) {
            return $this->sendError('Forbidden', [], 403);
        }
        $item = MarketingEvent::findOrFail($id);
        $item->delete();

        return $this->sendResponse(['deleted' => true], 'Event deleted.');
    }
}
