<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogPostController extends BaseController
{
    /**
     * GET /api/v1/blog/posts
     * List published blog posts with optional filters.
     */
    public function index(Request $request)
    {
        $query = BlogPost::where('status', 'published')
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

        return $this->sendResponse($posts, 'Blog posts retrieved successfully.');
    }

    /**
     * GET /api/v1/blog/posts/featured
     * Returns featured (most viewed) blog posts.
     */
    public function featured(Request $request)
    {
        $limit = $request->input('limit', 6);

        $posts = BlogPost::where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['author:id,first_name,last_name,profile_photo', 'categories:id,name,slug'])
            ->orderBy('views_count', 'desc')
            ->limit($limit)
            ->get();

        return $this->sendResponse($posts, 'Featured posts retrieved successfully.');
    }

    /**
     * GET /api/v1/blog/posts/{slug}
     * Returns a single blog post by slug or UUID.
     */
    public function show($slug)
    {
        $post = BlogPost::where('slug', $slug)
            ->orWhere('uuid', $slug)
            ->where('status', 'published')
            ->with(['author:id,first_name,last_name,profile_photo', 'categories:id,name,slug'])
            ->first();

        if (! $post) {
            return $this->sendError('Blog post not found.', [], 404);
        }

        return $this->sendResponse($post, 'Blog post retrieved successfully.');
    }

    /**
     * GET /api/v1/blog/categories
     * Returns all blog categories.
     */
    public function categories()
    {
        $categories = BlogCategory::withCount(['posts' => function ($q) {
            $q->where('status', 'published');
        }])->orderBy('name')->get();

        return $this->sendResponse($categories, 'Blog categories retrieved successfully.');
    }

    /**
     * POST /api/v1/blog/posts/{uuid}/view
     * Increments view count for a blog post.
     */
    public function incrementView($uuid)
    {
        $post = BlogPost::where('uuid', $uuid)
            ->orWhere('slug', $uuid)
            ->first();

        if (! $post) {
            return $this->sendError('Blog post not found.', [], 404);
        }

        $post->increment('views_count');

        return $this->sendResponse(['views_count' => $post->views_count], 'View count updated.');
    }

    /**
     * GET /api/v1/blog/posts/{uuid}/related
     * Returns related posts based on shared categories.
     */
    public function related($uuid, Request $request)
    {
        $post = BlogPost::where('uuid', $uuid)
            ->orWhere('slug', $uuid)
            ->with('categories:id')
            ->first();

        if (! $post) {
            return $this->sendError('Blog post not found.', [], 404);
        }

        $categoryIds = $post->categories->pluck('id');
        $limit = $request->input('limit', 3);

        $related = BlogPost::where('status', 'published')
            ->where('id', '!=', $post->id)
            ->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('blog_categories.id', $categoryIds);
            })
            ->with(['author:id,first_name,last_name', 'categories:id,name,slug'])
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        return $this->sendResponse($related, 'Related posts retrieved successfully.');
    }
}
