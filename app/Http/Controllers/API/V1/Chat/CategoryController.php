<?php

namespace App\Http\Controllers\API\V1\Chat;

use App\Http\Controllers\Controller;
use App\Models\AIChat;
use App\Models\ChatCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Public list of chat categories (used on marketing/demo chat shell).
     */
    public function index(): JsonResponse
    {
        $items = ChatCategory::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(fn (ChatCategory $c) => $this->formatCategory($c));

        return response()->json([
            'success' => true,
            'data' => $items,
            'status_code' => 200,
        ]);
    }

    /**
     * Personalized category list for authenticated users.
     * Categories are reordered by relevance to the user's conversation history.
     */
    public function personalized(Request $request): JsonResponse
    {
        $user = $request->user();

        $categories = ChatCategory::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        // Fetch last 200 user messages to analyse topic patterns
        $userText = AIChat::where('user_id', $user->id)
            ->where('sender', 'user')
            ->latest()
            ->limit(200)
            ->pluck('message')
            ->implode(' ');

        if (! empty(trim($userText))) {
            $text = strtolower($userText);

            $categories->each(function (ChatCategory $cat) use ($text) {
                // Build keyword set from label + slug words (skip 1–2 char tokens)
                $tokens = preg_split('/[\s\-_\/]+/', strtolower($cat->label.' '.$cat->slug));
                $keywords = array_filter($tokens, fn ($kw) => strlen($kw) > 2);

                $score = 0;
                foreach ($keywords as $kw) {
                    $score += substr_count($text, $kw);
                }
                $cat->relevance_score = $score;
            });

            // Highest relevance first; within the same score keep original sort_order
            $categories = $categories->sortByDesc('relevance_score');
        }

        $items = $categories->values()->map(fn (ChatCategory $c) => $this->formatCategory($c));

        return response()->json([
            'success' => true,
            'data' => $items,
            'status_code' => 200,
        ]);
    }

    private function formatCategory(ChatCategory $c): array
    {
        return [
            'id' => (string) $c->id,
            'slug' => $c->slug,
            'label' => $c->label,
            'icon' => $c->icon,
            'count' => $c->count,
            'isActive' => (bool) $c->is_active,
        ];
    }
}
