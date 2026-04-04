<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\EditorialCategory;
use App\Models\EditorialPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EditorialPostController extends BaseController
{
    /**
     * GET /api/v1/admin/editorial/posts
     * List all editorial posts (all statuses) with pagination.
     */
    public function index(Request $request)
    {
        $query = EditorialPost::with(['author:id,first_name,last_name', 'categories:id,name,slug'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $request->category));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('excerpt', 'like', "%{$search}%")
            );
        }

        $posts = $query->paginate($request->input('per_page', 15));

        return $this->sendResponse($posts, 'Editorial posts retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/editorial/posts/{id}
     */
    public function show($id)
    {
        $post = EditorialPost::with(['author:id,first_name,last_name', 'categories:id,name,slug'])
            ->where('id', $id)->orWhere('uuid', $id)->first();

        if (! $post) {
            return $this->sendError('Editorial post not found.', [], 404);
        }

        return $this->sendResponse($post, 'Editorial post retrieved successfully.');
    }

    /**
     * POST /api/v1/admin/editorial/posts
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all() + $request->allFiles(), [
            'title'              => 'required|string|max:255',
            'content'            => 'required|string',
            'excerpt'            => 'nullable|string|max:500',
            'featured_image'     => 'nullable|string|max:2048',
            'image'              => 'nullable|file|image|max:5120', // upload during creation
            'status'             => 'required|in:draft,published,archived',
            'category_ids'       => 'nullable|array',
            'category_ids.*'     => 'exists:editorial_categories,id',
            'read_time_minutes'  => 'nullable|integer|min:1',
            'seo_meta'           => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // Resolve featured image: file upload takes priority over URL string
        $featuredImage = $request->featured_image;
        if ($request->hasFile('image')) {
            $userId = $request->user()->id;
            $path = $request->file('image')->store("editorial/{$userId}", 'public');
            $featuredImage = Storage::url($path);
        }

        $post = EditorialPost::create([
            'author_id'          => $request->user()->id,
            'title'              => $request->title,
            'slug'               => Str::slug($request->title),
            'excerpt'            => $request->excerpt,
            'content'            => $request->content,
            'featured_image'     => $featuredImage,
            'status'             => $request->status,
            'published_at'       => $request->status === 'published' ? now() : null,
            'read_time_minutes'  => $request->read_time_minutes,
            'seo_meta'           => $request->seo_meta,
        ]);

        if ($request->filled('category_ids')) {
            $post->categories()->sync($request->category_ids);
        }

        return $this->sendResponse(
            $post->load(['author:id,first_name,last_name', 'categories:id,name,slug']),
            'Editorial post created successfully.',
            201
        );
    }

    /**
     * PUT /api/v1/admin/editorial/posts/{id}
     */
    public function update(Request $request, $id)
    {
        $post = EditorialPost::where('id', $id)->orWhere('uuid', $id)->first();

        if (! $post) {
            return $this->sendError('Editorial post not found.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'excerpt' => 'nullable|string|max:500',
            'featured_image' => 'nullable|string|max:2048',
            'status' => 'sometimes|in:draft,published,archived',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:editorial_categories,id',
            'read_time_minutes' => 'nullable|integer|min:1',
            'seo_meta' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $request->except(['category_ids']);

        if ($request->filled('title')) {
            $data['slug'] = Str::slug($request->title);
        }

        if ($request->input('status') === 'published' && ! $post->published_at) {
            $data['published_at'] = now();
        } elseif ($request->input('status') !== 'published') {
            $data['published_at'] = null;
        }

        $post->update($data);

        if ($request->has('category_ids')) {
            $post->categories()->sync($request->category_ids ?? []);
        }

        return $this->sendResponse(
            $post->load(['author:id,first_name,last_name', 'categories:id,name,slug']),
            'Editorial post updated successfully.'
        );
    }

    /**
     * DELETE /api/v1/admin/editorial/posts/{id}
     */
    public function destroy($id)
    {
        $post = EditorialPost::where('id', $id)->orWhere('uuid', $id)->first();

        if (! $post) {
            return $this->sendError('Editorial post not found.', [], 404);
        }

        $post->categories()->detach();
        $post->delete();

        return $this->sendResponse([], 'Editorial post deleted successfully.');
    }

    /**
     * POST /api/v1/admin/editorial/posts/{id}/publish
     */
    public function publish($id)
    {
        $post = EditorialPost::where('id', $id)->orWhere('uuid', $id)->first();

        if (! $post) {
            return $this->sendError('Editorial post not found.', [], 404);
        }

        $post->update(['status' => 'published', 'published_at' => $post->published_at ?? now()]);

        return $this->sendResponse($post, 'Editorial post published successfully.');
    }

    /**
     * POST /api/v1/admin/editorial/posts/{id}/unpublish
     */
    public function unpublish($id)
    {
        $post = EditorialPost::where('id', $id)->orWhere('uuid', $id)->first();

        if (! $post) {
            return $this->sendError('Editorial post not found.', [], 404);
        }

        $post->update(['status' => 'draft', 'published_at' => null]);

        return $this->sendResponse($post, 'Editorial post unpublished successfully.');
    }

    /**
     * GET /api/v1/admin/editorial/categories
     * All categories (including empty ones).
     */
    public function categories()
    {
        $categories = EditorialCategory::withCount('posts')->orderBy('name')->get();

        return $this->sendResponse($categories, 'Editorial categories retrieved successfully.');
    }

    /**
     * POST /api/v1/admin/editorial/categories
     */
    public function storeCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:editorial_categories,name',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $category = EditorialCategory::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
        ]);

        return $this->sendResponse($category, 'Category created successfully.', 201);
    }

    /**
     * POST /api/v1/admin/editorial/posts/{id}/image
     * Upload a featured image for an editorial post (multipart/form-data).
     */
    public function uploadImage(Request $request, $id)
    {
        $post = EditorialPost::where('id', $id)->orWhere('uuid', $id)->first();

        if (! $post) {
            return $this->sendError('Editorial post not found.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|file|image|max:5120', // 5 MB max
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // Remove the old image if it lives in our storage
        if ($post->getRawOriginal('featured_image') && str_starts_with($post->getRawOriginal('featured_image'), '/storage/editorial/')) {
            $oldPath = ltrim(str_replace('/storage/', '', $post->getRawOriginal('featured_image')), '/');
            Storage::disk('public')->delete($oldPath);
        }

        $userId = $request->user()->id;
        $path = $request->file('image')->store("editorial/{$userId}", 'public');
        $url = Storage::url($path); // e.g. /storage/editorial/1/abc.jpg

        $post->update(['featured_image' => $url]);

        return $this->sendResponse(
            ['featured_image' => $post->featured_image], // accessor adds full APP_URL
            'Featured image uploaded successfully.'
        );
    }

    /**
     * DELETE /api/v1/admin/editorial/categories/{id}
     */
    public function destroyCategory($id)
    {
        $category = EditorialCategory::find($id);

        if (! $category) {
            return $this->sendError('Category not found.', [], 404);
        }

        $category->delete();

        return $this->sendResponse([], 'Category deleted successfully.');
    }
}
