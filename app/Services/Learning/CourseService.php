<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CourseService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Course::query()->with('instructor:id,first_name,last_name');

        if (array_key_exists('search', $filters) && $filters['search']) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%");
            });
        }

        if (array_key_exists('is_published', $filters)) {
            $query->where('is_published', (bool) $filters['is_published']);
        }

        if (array_key_exists('level', $filters) && $filters['level']) {
            $query->where('level', $filters['level']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): Course
    {
        $data['slug'] = $this->generateUniqueSlug($data['title']);

        return Course::create($data);
    }

    public function update(Course $course, array $data): Course
    {
        if (array_key_exists('title', $data) && $data['title'] && $data['title'] !== $course->title) {
            $data['slug'] = $this->generateUniqueSlug($data['title'], $course->id);
        }
        $course->update($data);

        return $course->fresh();
    }

    public function delete(Course $course): void
    {
        $course->delete();
    }

    public function enroll(Course $course, User $user): CourseEnrollment
    {
        $existing = CourseEnrollment::where('course_id', $course->id)
            ->where('user_id', $user->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        return CourseEnrollment::create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'enrolled_at' => now(),
            'progress_percentage' => 0,
        ]);
    }

    protected function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 1;
        while (Course::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
