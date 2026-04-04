<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface BaseRepositoryInterface
{
    public function all(): Collection;

    public function find(string $id): ?Model;

    public function create(array $data): Model;

    public function update(string $id, array $data): bool;

    public function delete(string $id): bool;
}
