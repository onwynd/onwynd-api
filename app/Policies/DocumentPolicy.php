<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admins can view all documents
        if ($user->hasRole('admin')) {
            return true;
        }

        return true; // Users can view their own lists
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Document $document): bool
    {
        // Admin Override: Admin has utmost privileges to view it, except otherwise stated.
        // If we needed "except otherwise stated", we'd check a flag like $document->is_admin_restricted
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->id === $document->owner_id ||
               $document->permissions()->where('user_id', $user->id)->whereIn('permission', ['view', 'edit', 'owner'])->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Document $document): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->id === $document->owner_id ||
               $document->permissions()->where('user_id', $user->id)->whereIn('permission', ['edit', 'owner'])->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Document $document): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->id === $document->owner_id ||
               $document->permissions()->where('user_id', $user->id)->where('permission', 'owner')->exists();
    }
}
