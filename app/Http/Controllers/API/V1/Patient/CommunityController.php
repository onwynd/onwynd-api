<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class CommunityController extends BaseController
{
    /**
     * Get community feed.
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);
        $filter = $request->input('filter', 'all'); // all, following, popular

        // Cache feed for 1 minute to improve performance
        $result = Cache::remember("community_feed_{$filter}_{$page}", 60, function () use ($page, $perPage) {
            // Mock Feed Data
            $posts = [
                [
                    'id' => 1,
                    'user' => [
                        'id' => 101,
                        'name' => 'Sarah J.',
                        'avatar' => 'https://via.placeholder.com/150',
                        'badge' => 'Wellness Warrior',
                    ],
                    'content' => 'Just finished my first 7-day meditation streak! Feeling so much calmer. 🧘‍♀️✨',
                    'image_url' => null,
                    'likes_count' => 24,
                    'comments_count' => 5,
                    'is_liked' => true,
                    'created_at' => '2 hours ago',
                    'topics' => ['Meditation', 'Mindfulness'],
                ],
                [
                    'id' => 2,
                    'user' => [
                        'id' => 102,
                        'name' => 'Mike T.',
                        'avatar' => 'https://via.placeholder.com/150',
                        'badge' => 'Newcomer',
                    ],
                    'content' => 'Struggling with sleep lately. Any tips for winding down without screens?',
                    'image_url' => null,
                    'likes_count' => 12,
                    'comments_count' => 8,
                    'is_liked' => false,
                    'created_at' => '5 hours ago',
                    'topics' => ['Sleep', 'Advice'],
                ],
                [
                    'id' => 3,
                    'user' => [
                        'id' => 103,
                        'name' => 'Emily R.',
                        'avatar' => 'https://via.placeholder.com/150',
                        'badge' => 'Expert',
                    ],
                    'content' => 'Morning hike views! Nature is the best therapy.',
                    'image_url' => 'https://via.placeholder.com/600x400',
                    'likes_count' => 156,
                    'comments_count' => 12,
                    'is_liked' => false,
                    'created_at' => '1 day ago',
                    'topics' => ['Nature', 'Exercise'],
                ],
            ];

            return [
                'data' => $posts,
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 100,
                    'total_pages' => 10,
                ],
            ];
        });

        return $this->sendResponse($result, 'Community feed retrieved successfully.');
    }

    /**
     * Create a new post.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'image' => 'nullable|image|max:5120', // 5MB
            'topics' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // Mock Post Creation
        $post = [
            'id' => rand(100, 999),
            'user' => [
                'id' => $request->user()->id ?? 1,
                'name' => $request->user()->name ?? 'Me',
                'avatar' => 'https://via.placeholder.com/150',
            ],
            'content' => $request->content,
            'image_url' => $request->hasFile('image') ? 'https://via.placeholder.com/600x400' : null,
            'likes_count' => 0,
            'comments_count' => 0,
            'is_liked' => false,
            'created_at' => 'Just now',
            'topics' => $request->topics ?? [],
        ];

        return $this->sendResponse($post, 'Post created successfully.');
    }

    /**
     * Get a single post with comments.
     */
    public function show($id)
    {
        // Mock Single Post
        $post = [
            'id' => $id,
            'user' => [
                'id' => 101,
                'name' => 'Sarah J.',
                'avatar' => 'https://via.placeholder.com/150',
                'badge' => 'Wellness Warrior',
            ],
            'content' => 'Just finished my first 7-day meditation streak! Feeling so much calmer. 🧘‍♀️✨',
            'image_url' => null,
            'likes_count' => 24,
            'comments_count' => 5,
            'is_liked' => true,
            'created_at' => '2 hours ago',
            'topics' => ['Meditation', 'Mindfulness'],
            'comments' => [
                [
                    'id' => 1,
                    'user' => ['name' => 'Tom', 'avatar' => 'https://via.placeholder.com/50'],
                    'content' => 'Way to go! Keep it up.',
                    'created_at' => '1 hour ago',
                ],
                [
                    'id' => 2,
                    'user' => ['name' => 'Alice', 'avatar' => 'https://via.placeholder.com/50'],
                    'content' => 'I need to start this habit too.',
                    'created_at' => '30 mins ago',
                ],
            ],
        ];

        return $this->sendResponse($post, 'Post details retrieved successfully.');
    }

    /**
     * Like or unlike a post.
     */
    public function like($id)
    {
        return $this->sendResponse(['is_liked' => true, 'likes_count' => 25], 'Post liked.');
    }

    /**
     * Add a comment to a post.
     */
    public function comment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $comment = [
            'id' => rand(100, 999),
            'user' => [
                'id' => $request->user()->id ?? 1,
                'name' => $request->user()->name ?? 'Me',
                'avatar' => 'https://via.placeholder.com/50',
            ],
            'content' => $request->content,
            'created_at' => 'Just now',
        ];

        return $this->sendResponse($comment, 'Comment added successfully.');
    }

    /**
     * Delete a post.
     */
    public function destroy($id)
    {
        // In real app, check ownership
        return $this->sendResponse([], 'Post deleted successfully.');
    }
}
