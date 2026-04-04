<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ContentController extends BaseController
{
    public function index(Request $request)
    {
        $posts = BlogPost::with('author:id,first_name,last_name')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return $this->sendResponse($posts, 'Content retrieved successfully.');
    }

    public function show($id)
    {
        $post = BlogPost::find($id);

        if (! $post) {
            return $this->sendError('Post not found.');
        }

        return $this->sendResponse($post, 'Post details retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:blog_categories,id',
            'tags' => 'nullable|array', // Assuming stored in seo_meta or separate table later
            'status' => 'required|in:draft,published,archived',
            'featured_image' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $post = BlogPost::create([
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'content' => $request->content,
            'author_id' => $request->user()->id,
            // 'category' removed as it is not in fillable
            // 'tags' removed as it is not in fillable
            'status' => $request->status,
            'featured_image' => $request->featured_image,
            'published_at' => $request->status === 'published' ? now() : null,
            'seo_meta' => $request->tags ? ['tags' => $request->tags] : null, // Storing tags in seo_meta for now
        ]);

        if ($request->has('category_ids')) {
            $post->categories()->sync($request->category_ids);
        }

        return $this->sendResponse($post->load('categories'), 'Post created successfully.');
    }

    public function update(Request $request, $id)
    {
        $post = BlogPost::find($id);

        if (! $post) {
            return $this->sendError('Post not found.');
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:blog_categories,id',
            'tags' => 'nullable|array',
            'status' => 'sometimes|in:draft,published,archived',
            'featured_image' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $request->except(['category_ids', 'tags']);

        if (isset($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }
        if (isset($data['status']) && $data['status'] === 'published' && ! $post->published_at) {
            $data['published_at'] = now();
        }

        if ($request->has('tags')) {
            $seoMeta = $post->seo_meta ?? [];
            $seoMeta['tags'] = $request->tags;
            $data['seo_meta'] = $seoMeta;
        }

        $post->update($data);

        if ($request->has('category_ids')) {
            $post->categories()->sync($request->category_ids);
        }

        return $this->sendResponse($post->load('categories'), 'Post updated successfully.');
    }

    public function destroy($id)
    {
        $post = BlogPost::find($id);

        if (! $post) {
            return $this->sendError('Post not found.');
        }

        $post->delete();

        return $this->sendResponse([], 'Post deleted successfully.');
    }
}
