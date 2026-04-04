<?php

namespace App\Policies;

use App\Models\KnowledgeBaseArticle;
use App\Models\User;

class KnowledgeBaseArticlePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        // Public access allowed for list, filtering happens in controller query
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, KnowledgeBaseArticle $article): bool
    {
        // Admin can view anything
        if ($user && $user->hasRole('admin')) {
            return true;
        }

        // Article must be published for non-admins
        if ($article->status !== 'published' || $article->published_at > now()) {
            return false;
        }

        // Check visibility
        if ($article->visibility === 'public') {
            return true;
        }

        // If user is not logged in, they can only see public (already handled above)
        if (! $user) {
            return false;
        }

        // Corporate visibility
        if ($article->visibility === 'corporate') {
            return $user->hasRole(['admin', 'corporate_admin', 'corporate_employee']); // Assuming these roles exist or logic similar
        }

        // Internal visibility
        if ($article->visibility === 'internal') {
            return $user->hasRole(['admin', 'employee', 'therapist']); // Internal staff
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, KnowledgeBaseArticle $article): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, KnowledgeBaseArticle $article): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, KnowledgeBaseArticle $article): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, KnowledgeBaseArticle $article): bool
    {
        return $user->hasRole('admin');
    }
}
