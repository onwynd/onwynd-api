<?php

namespace App\Http\Controllers\API\V1\KnowledgeBase;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KnowledgeBaseController extends Controller
{
    /**
     * Get FAQ data structured for frontend.
     */
    public function faq()
    {
        $categories = KnowledgeBaseCategory::where('order', 0)
            ->with(['articles' => function ($query) {
                $query->where('status', 'published')
                    ->where('visibility', 'public')
                    ->orderBy('order')
                    ->select('id', 'category_id', 'title as question', 'content as answer');
            }])
            ->orderBy('id')
            ->get()
            ->map(function ($category) {
                return [
                    'title' => $category->name,
                    'icon' => $category->icon,
                    'questions' => $category->articles,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    /**
     * Get Knowledge Base Topics (Public Categories).
     */
    public function topics()
    {
        $categories = KnowledgeBaseCategory::where('order', '>', 0)
            ->where('type', 'public')
            ->select('id', 'name', 'slug', 'icon', 'description')
            ->orderBy('order')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    /**
     * Get Corporate Knowledge Base Topics (Internal/Modus Operandi).
     */
    public function corporate()
    {
        // Add auth check here if needed, or rely on route middleware
        $categories = KnowledgeBaseCategory::where('type', 'corporate')
            ->select('id', 'name', 'slug', 'icon', 'description')
            ->orderBy('order')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    /**
     * Display a listing of the categories.
     */
    public function categories()
    {
        $categories = KnowledgeBaseCategory::whereNull('parent_id')
            ->with('children')
            ->orderBy('order')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    /**
     * Display a listing of the articles.
     */
    public function index(Request $request)
    {
        $query = KnowledgeBaseArticle::query()->with(['category', 'author']);

        // Search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        // Filter by Category
        if ($request->has('category_slug')) {
            $category = KnowledgeBaseCategory::where('slug', $request->input('category_slug'))->first();
            if ($category) {
                // Include articles from children categories too?
                // For now, simpler: just this category or subcategories
                $categoryIds = $category->children()->pluck('id')->push($category->id);
                $query->whereIn('category_id', $categoryIds);
            }
        }

        // Filter by Tag
        if ($request->has('tag')) {
            $query->whereJsonContains('tags', $request->input('tag'));
        }

        // Visibility Scope Logic
        $user = Auth::guard('sanctum')->user(); // Assuming sanctum auth

        if ($user && $user->hasRole('admin')) {
            // Admin sees all (including drafts if filtered by status, otherwise all)
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }
        } else {
            // Non-admins only see published
            $query->published();

            if (! $user) {
                // Public only
                $query->public();
            } else {
                // Check roles for visibility
                $visibilities = ['public'];

                if ($user->hasRole(['admin', 'corporate_admin', 'corporate_employee'])) {
                    $visibilities[] = 'corporate';
                }

                if ($user->hasRole(['admin', 'employee', 'therapist'])) {
                    $visibilities[] = 'internal';
                }

                $query->whereIn('visibility', $visibilities);
            }
        }

        $articles = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json($articles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', KnowledgeBaseArticle::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'summary' => 'nullable|string|max:500',
            'category_id' => 'required|exists:knowledge_base_categories,id',
            'status' => 'required|in:draft,published,archived',
            'visibility' => 'required|in:public,internal,corporate',
            'tags' => 'nullable|array',
            'published_at' => 'nullable|date',
        ]);

        $validated['author_id'] = Auth::id();
        if ($validated['status'] === 'published' && ! isset($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        $article = KnowledgeBaseArticle::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Article created successfully',
            'data' => $article,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($slug)
    {
        // Allow finding by ID or Slug
        $article = KnowledgeBaseArticle::where('slug', $slug)
            ->orWhere('id', $slug)
            ->with(['category', 'author'])
            ->firstOrFail();

        $this->authorize('view', $article);

        // Increment views
        $article->increment('views');

        return response()->json([
            'status' => 'success',
            'data' => $article,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $article = KnowledgeBaseArticle::findOrFail($id);
        $this->authorize('update', $article);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'summary' => 'nullable|string|max:500',
            'category_id' => 'sometimes|exists:knowledge_base_categories,id',
            'status' => 'sometimes|in:draft,published,archived',
            'visibility' => 'sometimes|in:public,internal,corporate',
            'tags' => 'nullable|array',
            'published_at' => 'nullable|date',
        ]);

        $article->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Article updated successfully',
            'data' => $article,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $article = KnowledgeBaseArticle::findOrFail($id);
        $this->authorize('delete', $article);

        $article->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Article deleted successfully',
        ]);
    }

    /**
     * Mark article as helpful/not helpful
     */
    public function feedback(Request $request, string $id)
    {
        $article = KnowledgeBaseArticle::findOrFail($id);
        // Authorization: basic view check
        $this->authorize('view', $article);

        $request->validate([
            'type' => 'required|in:helpful,not_helpful',
        ]);

        if ($request->type === 'helpful') {
            $article->increment('helpful_count');
        } else {
            $article->increment('not_helpful_count');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Feedback recorded',
        ]);
    }
}
