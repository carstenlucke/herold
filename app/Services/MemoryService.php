<?php

namespace App\Services;

use App\Models\Memory;
use Illuminate\Database\Eloquent\Collection;

class MemoryService
{
    public function search(?string $scope, ?string $category, ?string $query): Collection
    {
        return Memory::query()
            ->when($scope, fn ($q) => $q->where('scope', $scope))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($query, fn ($q) => $q->where('content', 'like', "%{$query}%"))
            ->latest()
            ->get();
    }

    public function store(string $scope, string $category, string $content, string $source): Memory
    {
        return Memory::query()->create(compact('scope', 'category', 'content', 'source'));
    }

    public function destroy(Memory $memory): void
    {
        $memory->delete();
    }
}
