<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Course;
use App\Services\Learning\CourseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CourseController extends BaseController
{
    protected CourseService $service;

    public function __construct(CourseService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $courses = $this->service->list([
            'search' => $request->get('search'),
            'is_published' => $request->get('is_published'),
            'level' => $request->get('level'),
            'per_page' => $request->get('per_page', 15),
        ]);

        return $this->sendResponse($courses, 'Courses retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail_url' => 'nullable|url',
            'instructor_id' => 'nullable|exists:users,id',
            'duration_minutes' => 'nullable|integer|min:0',
            'level' => 'nullable|string|max:50',
            'is_published' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        $course = $this->service->create($validator->validated());

        return $this->sendResponse($course, 'Course created successfully.', 201);
    }

    public function show($id)
    {
        $course = Course::with(['instructor:id,first_name,last_name', 'modules.lessons'])->find($id);
        if (! $course) {
            return $this->sendError('Course not found.', [], 404);
        }

        return $this->sendResponse($course, 'Course retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $course = Course::find($id);
        if (! $course) {
            return $this->sendError('Course not found.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail_url' => 'nullable|url',
            'instructor_id' => 'nullable|exists:users,id',
            'duration_minutes' => 'nullable|integer|min:0',
            'level' => 'nullable|string|max:50',
            'is_published' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        $updated = $this->service->update($course, $validator->validated());

        return $this->sendResponse($updated, 'Course updated successfully.');
    }

    public function destroy($id)
    {
        $course = Course::find($id);
        if (! $course) {
            return $this->sendError('Course not found.', [], 404);
        }
        $this->service->delete($course);

        return $this->sendResponse([], 'Course deleted successfully.');
    }
}
