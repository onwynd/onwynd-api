<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\Course;
use App\Services\Learning\CourseService;
use Illuminate\Http\Request;

class CourseCatalogController extends BaseController
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
            'is_published' => true,
            'level' => $request->get('level'),
            'per_page' => $request->get('per_page', 12),
        ]);

        return $this->sendResponse($courses, 'Courses retrieved successfully.');
    }

    public function show($identifier)
    {
        $course = Course::with(['modules.lessons', 'instructor:id,first_name,last_name'])
            ->where('uuid', $identifier)
            ->orWhere('slug', $identifier)
            ->where('is_published', true)
            ->first();

        if (! $course) {
            return $this->sendError('Course not found.', [], 404);
        }

        return $this->sendResponse($course, 'Course retrieved successfully.');
    }

    public function enroll(Request $request, $identifier)
    {
        $course = Course::where('uuid', $identifier)
            ->orWhere('slug', $identifier)
            ->where('is_published', true)
            ->first();
        if (! $course) {
            return $this->sendError('Course not found.', [], 404);
        }
        $enrollment = $this->service->enroll($course, $request->user());

        return $this->sendResponse($enrollment, 'Enrolled successfully.');
    }
}
