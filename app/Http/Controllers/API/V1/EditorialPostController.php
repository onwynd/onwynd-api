<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Models\EditorialCategory;
use App\Models\EditorialPost;
use Illuminate\Http\Request;

class EditorialPostController extends BaseController
{
    /**
     * GET /api/v1/editorial/posts
     * List published editorial posts with optional filters.
     */
    public function index(Request $request)
    {
        $query = EditorialPost::where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['author:id,first_name,last_name,profile_photo', 'categories:id,name,slug']);

        if ($request->has('category')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->input('sort_by', 'latest');
        if ($sortBy === 'popular') {
            $query->orderBy('views_count', 'desc');
        } else {
            $query->orderBy('published_at', 'desc');
        }

        $posts = $query->paginate($request->input('per_page', 10));

        return $this->sendResponse($posts, 'Editorial posts retrieved successfully.');
    }

    /**
     * GET /api/v1/editorial/posts/featured
     * Returns featured (most viewed) editorial posts.
     */
    public function featured(Request $request)
    {
        $limit = $request->input('limit', 6);

        $posts = EditorialPost::where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['author:id,first_name,last_name,profile_photo', 'categories:id,name,slug'])
            ->orderBy('views_count', 'desc')
            ->limit($limit)
            ->get();

        return $this->sendResponse($posts, 'Featured editorial posts retrieved successfully.');
    }

    /**
     * GET /api/v1/editorial/posts/{slug}
     * Returns a single editorial post by slug or UUID.
     */
    public function show($slug)
    {
        $post = EditorialPost::where('slug', $slug)
            ->orWhere('uuid', $slug)
            ->where('status', 'published')
            ->with(['author:id,first_name,last_name,profile_photo', 'categories:id,name,slug'])
            ->first();

        if (! $post) {
            return $this->sendError('Editorial post not found.', [], 404);
        }

        return $this->sendResponse($post, 'Editorial post retrieved successfully.');
    }

    /**
     * GET /api/v1/editorial/categories
     * Returns all editorial categories.
     */
    public function categories()
    {
        $categories = EditorialCategory::withCount(['posts' => function ($q) {
            $q->where('status', 'published');
        }])->orderBy('name')->get();

        return $this->sendResponse($categories, 'Editorial categories retrieved successfully.');
    }

    /**
     * POST /api/v1/editorial/posts/{uuid}/view
     * Increments view count for an editorial post.
     */
    public function incrementView($uuid)
    {
        $post = EditorialPost::where('uuid', $uuid)
            ->orWhere('slug', $uuid)
            ->first();

        if (! $post) {
            return $this->sendError('Editorial post not found.', [], 404);
        }

        $post->increment('views_count');

        return $this->sendResponse(['views_count' => $post->views_count], 'View count updated.');
    }

    /**
     * GET /api/v1/editorial/posts/{uuid}/related
     * Returns related posts based on shared categories.
     */
    public function related($uuid, Request $request)
    {
        $post = EditorialPost::where('uuid', $uuid)
            ->orWhere('slug', $uuid)
            ->with('categories:id')
            ->first();

        if (! $post) {
            return $this->sendError('Editorial post not found.', [], 404);
        }

        $categoryIds = $post->categories->pluck('id');
        $limit = $request->input('limit', 3);

        $related = EditorialPost::where('status', 'published')
            ->where('id', '!=', $post->id)
            ->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('editorial_categories.id', $categoryIds);
            })
            ->with(['author:id,first_name,last_name', 'categories:id,name,slug'])
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        return $this->sendResponse($related, 'Related editorial posts retrieved successfully.');
    }
}
