<?php

namespace App\Observers;

use App\Events\UserCreated;
use App\Events\UserDeleted;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "created" event
     */
    public function created(User $user): void
    {
        Log::info('User created', ['user_id' => $user->id, 'email' => $user->email]);

        event(new UserCreated($user));
    }

    /**
     * Handle the User "updated" event
     */
    public function updated(User $user): void
    {
        Log::info('User updated', ['user_id' => $user->id]);
    }

    /**
     * Handle the User "deleted" event
     */
    public function deleted(User $user): void
    {
        Log::warning('User deleted', ['user_id' => $user->id, 'email' => $user->email]);

        event(new UserDeleted($user));
    }

    /**
     * Handle the User "restored" event
     */
    public function restored(User $user): void
    {
        Log::info('User restored', ['user_id' => $user->id]);
    }

    /**
     * Handle the User "force deleted" event
     */
    public function forceDeleted(User $user): void
    {
        Log::warning('User force deleted', ['user_id' => $user->id]);
    }
}
