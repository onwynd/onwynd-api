<?php

namespace App\Services\Recommendation;

use App\Models\KnowledgeBaseArticle;
use App\Models\User;
use Illuminate\Support\Collection;

class ContentRecommendationService
{
    public function recommendForUser(User $user, int $limit = 5): Collection
    {
        $goals = collect($user->mental_health_goals ?? []);
        $preferences = collect($user->preferences ?? []);
        $tags = $goals->merge(collect($preferences->get('topics', [])))->filter()->map(fn ($t) => strtolower((string) $t))->unique();

        $query = KnowledgeBaseArticle::query()->where('status', 'published');

        if ($tags->isNotEmpty()) {
            foreach ($tags as $tag) {
                $query->orWhereJsonContains('tags', $tag);
            }
        }

        $articles = $query->orderByDesc('published_at')->limit(50)->get();

        $scored = $articles->map(function (KnowledgeBaseArticle $a) use ($tags) {
            $artTags = collect($a->tags ?? [])->map(fn ($t) => strtolower((string) $t));
            $overlap = $artTags->intersect($tags);
            $score = $overlap->count() * 5 + (int) ($a->views ?? 0) / 100 + (int) ($a->helpful_count ?? 0);
            $a->match_score = $score;

            return $a;
        });

        return $scored->sortByDesc('match_score')->take($limit)->values();
    }
}
