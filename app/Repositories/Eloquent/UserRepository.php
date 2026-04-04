<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class UserRepository implements UserRepositoryInterface
{
    public function all(): Collection
    {
        return User::all();
    }

    public function find(string $id): ?Model
    {
        return User::find($id);
    }

    public function create(array $data): Model
    {
        return User::create($data);
    }

    public function update(string $id, array $data): bool
    {
        $user = User::find($id);
        if ($user) {
            return $user->update($data);
        }

        return false;
    }

    public function delete(string $id): bool
    {
        return (bool) User::destroy($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function getTherapists(): mixed
    {
        return User::whereHas('role', function ($query) {
            $query->where('slug', 'therapist');
        })->get();
    }
}
