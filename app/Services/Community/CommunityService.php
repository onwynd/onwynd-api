<?php

namespace App\Services\Community;

use App\Models\Community;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CommunityService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Community::query();

        if (array_key_exists('search', $filters) && $filters['search']) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%")
                    ->orWhere('category', 'like', "%{$s}%");
            });
        }

        if (array_key_exists('category', $filters) && $filters['category']) {
            $query->where('category', $filters['category']);
        }

        if (array_key_exists('is_private', $filters)) {
            $query->where('is_private', (bool) $filters['is_private']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): Community
    {
        $data['slug'] = $this->generateUniqueSlug($data['name']);

        return Community::create($data);
    }

    public function update(Community $community, array $data): Community
    {
        if (array_key_exists('name', $data) && $data['name'] && $data['name'] !== $community->name) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $community->id);
        }
        $community->update($data);

        return $community->fresh();
    }

    public function delete(Community $community): void
    {
        $community->delete();
    }

    protected function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        while (Community::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
