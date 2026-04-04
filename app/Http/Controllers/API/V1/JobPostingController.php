<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\JobPosting;
use Illuminate\Http\Request;

class JobPostingController extends Controller
{
    /**
     * Public listing: active + recently-filled jobs.
     */
    public function index(Request $request)
    {
        $query = JobPosting::active();

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        if ($request->filled('location')) {
            $query->where('location', 'like', '%'.$request->location.'%');
        }

        $jobs = $query->latest('posted_at')->get();

        return response()->json([
            'status' => 'success',
            'data' => $jobs,
        ]);
    }

    /**
     * Public single job: allow viewing filled positions within the 7-day grace period.
     */
    public function show($slug)
    {
        // First try within the active (open + 7-day filled) window
        $job = JobPosting::active()->where('slug', $slug)->first();

        // If not found in active window, check if it exists at all so we can
        // return a more accurate "position filled" message vs "not found".
        if (! $job) {
            $filled = JobPosting::where('slug', $slug)
                ->whereIn('status', ['filled', 'closed'])
                ->first();

            if ($filled) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This position has been filled and is no longer accepting applications.',
                    'data' => null,
                ], 410); // 410 Gone
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Job posting not found.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $job,
        ]);
    }
}
