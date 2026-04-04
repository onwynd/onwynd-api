<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\LandingPageContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LandingPageContentController extends BaseController
{
    public function index(Request $request)
    {
        $content = LandingPageContent::query()
            ->when($request->section, function ($query, $section) {
                return $query->section($section);
            })
            ->when($request->active !== null, function ($query) use ($request) {
                return $query->where('is_active', $request->active);
            })
            ->orderBy('section')
            ->orderBy('key')
            ->get()
            ->groupBy('section');

        return $this->sendResponse($content, 'Landing page content retrieved successfully.');
    }

    public function show($id)
    {
        $content = LandingPageContent::find($id);

        if (! $content) {
            return $this->sendError('Content not found.');
        }

        return $this->sendResponse($content, 'Content details retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'section' => 'required|string|max:100',
            'key' => 'required|string|max:100',
            'value' => 'nullable|string',
            'metadata' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // Check if content with same section and key already exists
        $existing = LandingPageContent::where('section', $request->section)
            ->where('key', $request->key)
            ->first();

        if ($existing) {
            return $this->sendError('Content with this section and key already exists.', [], 422);
        }

        $content = LandingPageContent::create([
            'section' => $request->section,
            'key' => $request->key,
            'value' => $request->value,
            'metadata' => $request->metadata ?? [],
            'is_active' => $request->is_active ?? true,
        ]);

        return $this->sendResponse($content, 'Landing page content created successfully.');
    }

    public function update(Request $request, $id)
    {
        $content = LandingPageContent::find($id);

        if (! $content) {
            return $this->sendError('Content not found.');
        }

        $validator = Validator::make($request->all(), [
            'section' => 'sometimes|string|max:100',
            'key' => 'sometimes|string|max:100',
            'value' => 'nullable|string',
            'metadata' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // Check for duplicate section/key combination
        if ($request->has('section') || $request->has('key')) {
            $section = $request->section ?? $content->section;
            $key = $request->key ?? $content->key;

            $existing = LandingPageContent::where('section', $section)
                ->where('key', $key)
                ->where('id', '!=', $id)
                ->first();

            if ($existing) {
                return $this->sendError('Content with this section and key already exists.', [], 422);
            }
        }

        $content->update($request->only(['section', 'key', 'value', 'metadata', 'is_active']));

        return $this->sendResponse($content, 'Landing page content updated successfully.');
    }

    public function destroy($id)
    {
        $content = LandingPageContent::find($id);

        if (! $content) {
            return $this->sendError('Content not found.');
        }

        $content->delete();

        return $this->sendResponse([], 'Landing page content deleted successfully.');
    }

    public function getBySection(Request $request, $section)
    {
        $content = LandingPageContent::section($section)
            ->active()
            ->orderBy('key')
            ->get();

        return $this->sendResponse($content, "Landing page content for section '{$section}' retrieved successfully.");
    }

    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|array',
            'content.*.id' => 'required|exists:landing_page_content,id',
            'content.*.value' => 'nullable|string',
            'content.*.metadata' => 'nullable|array',
            'content.*.is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $updated = [];
        foreach ($request->content as $item) {
            $content = LandingPageContent::find($item['id']);
            if ($content) {
                $content->update($item);
                $updated[] = $content;
            }
        }

        return $this->sendResponse($updated, 'Landing page content updated successfully.');
    }
}
