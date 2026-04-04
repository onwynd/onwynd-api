<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository extends BaseRepository
{
    /**
     * Constructor
     */
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * Find user by email
     */
    public function findByEmail($email)
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Find user by phone
     */
    public function findByPhone($phone)
    {
        return $this->model->where('phone', $phone)->first();
    }

    /**
     * Get users by role
     */
    public function getByRole($role)
    {
        return $this->model->whereHas('role', function ($q) use ($role) {
            $q->where('slug', $role)->orWhere('name', $role);
        })->get();
    }

    /**
     * Get verified therapists
     */
    public function getVerifiedTherapists()
    {
        return $this->model
            ->whereHas('therapist', function ($query) {
                $query->where('is_verified', true);
            })
            ->get();
    }

    /**
     * Get active users
     */
    public function getActive()
    {
        return $this->model->where('is_active', true)->get();
    }

    /**
     * Check if email exists
     */
    public function emailExists($email, $exceptId = null)
    {
        $query = $this->model->where('email', $email);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    /**
     * Get users marked for deletion
     */
    public function getMarkedForDeletion()
    {
        return $this->model
            ->where('marked_for_deletion', true)
            ->where('deletion_scheduled_at', '<=', now())
            ->get();
    }

    /**
     * Get user statistics
     */
    public function getStats()
    {
        return [
            'total_users' => $this->model->count(),
            'active_users' => $this->model->where('is_active', true)->count(),
            'therapists' => $this->model->whereHas('therapist')->count(),
            'patients' => $this->model->whereHas('role', function ($q) {
                $q->where('slug', 'patient')->orWhere('name', 'patient');
            })->count(),
            'verified_therapists' => $this->model->whereHas('therapist', function ($query) {
                $query->where('is_verified', true);
            })->count(),
        ];
    }
}
