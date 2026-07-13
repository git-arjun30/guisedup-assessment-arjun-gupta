<?php

namespace App\Repositories;

use App\Models\Interaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InteractionRepository
{
    public function create(array $data): Interaction
    {
        return Interaction::query()->create($data);
    }

    /**
     * Count interactions between a viewer and posts authored by a given user.
     * Used as a proxy for relationship depth in feed ranking.
     */
    public function getRelationshipDepth(int $viewerId, int $authorId): float
    {
        $count = Interaction::query()
            ->where('interactions.user_id', $viewerId)
            ->whereHas('post', fn ($query) => $query->where('user_id', $authorId))
            ->count();

        // Normalize to 0-1 using a soft cap at 20 interactions
        return min(1.0, $count / 20.0);
    }

    /**
     * Batch relationship depths for multiple authors to avoid N+1 queries.
     *
     * @param  array<int>  $authorIds
     * @return array<int, float>
     */
    public function getRelationshipDepths(int $viewerId, array $authorIds): array
    {
        if ($authorIds === []) {
            return [];
        }

        $counts = Interaction::query()
            ->select('posts.user_id as author_id', DB::raw('COUNT(*) as interaction_count'))
            ->join('posts', 'posts.id', '=', 'interactions.post_id')
            ->where('interactions.user_id', $viewerId)
            ->whereIn('posts.user_id', $authorIds)
            ->groupBy('posts.user_id')
            ->pluck('interaction_count', 'author_id');

        $depths = [];
        foreach ($authorIds as $authorId) {
            $count = (int) ($counts[$authorId] ?? 0);
            $depths[$authorId] = min(1.0, $count / 20.0);
        }

        return $depths;
    }

    public function getRecentInteractionTexts(int $userId, int $limit = 10): Collection
    {
        return Interaction::query()
            ->with('post')
            ->where('user_id', $userId)
            ->whereHas('post')
            ->latest()
            ->limit($limit)
            ->get()
            ->pluck('post.content')
            ->filter();
    }
}
