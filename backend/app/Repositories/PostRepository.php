<?php

namespace App\Repositories;

use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PostRepository
{
    public function create(array $data): Post
    {
        return Post::query()->create($data);
    }

    public function findById(int $id): ?Post
    {
        return Post::query()->with('user')->find($id);
    }

    public function findByEmbeddingIds(array $embeddingIds, int $limit = 10): Collection
    {
        if ($embeddingIds === []) {
            return collect();
        }

        return Post::query()
            ->with('user')
            ->whereIn('embedding_id', $embeddingIds)
            ->limit($limit)
            ->get();
    }

    public function getFeedCandidates(int $excludeUserId, int $limit = 200): Collection
    {
        return Post::query()
            ->with(['user', 'interactions'])
            ->where('user_id', '!=', $excludeUserId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function paginateRanked(Collection $rankedPosts, int $perPage, int $page): LengthAwarePaginator
    {
        $items = $rankedPosts->forPage($page, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $rankedPosts->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}
