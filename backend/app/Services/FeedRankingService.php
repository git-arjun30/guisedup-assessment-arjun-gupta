<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Repositories\InteractionRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FeedRankingService
{
    // Weight constants for the composite ranking formula
    private const WEIGHT_RELATIONSHIP = 0.40;

    private const WEIGHT_SEMANTIC = 0.30;

    private const WEIGHT_AUTHENTICITY = 0.20;

    private const WEIGHT_RECENCY = 0.10;

    // Posts older than this many hours receive zero recency score
    private const RECENCY_HALF_LIFE_HOURS = 72;

    public function __construct(
        private readonly InteractionRepository $interactionRepository,
        private readonly EmbeddingService $embeddingService,
    ) {}

    /**
     * Rank posts for a personalized feed.
     *
     * Composite score formula:
     *   score = 0.40 * relationship_depth
     *         + 0.30 * semantic_similarity
     *         + 0.20 * authenticity_score
     *         + 0.10 * recency_score
     */
    public function rank(User $viewer, Collection $posts): Collection
    {
        if ($posts->isEmpty()) {
            return collect();
        }

        $authorIds = $posts->pluck('user_id')->unique()->values()->all();
        $relationshipDepths = $this->interactionRepository->getRelationshipDepths(
            $viewer->id,
            $authorIds
        );

        // Build a lightweight interest profile from the viewer's recent interactions
        $interestTexts = $this->interactionRepository
            ->getRecentInteractionTexts($viewer->id, 10)
            ->implode(' ');

        $interestEmbeddingIds = $interestTexts !== ''
            ? $this->embeddingService->search($interestTexts, 5)
            : [];

        return $posts
            ->map(function (Post $post) use ($relationshipDepths, $interestEmbeddingIds) {
                $relationshipDepth = $relationshipDepths[$post->user_id] ?? 0.0;
                $semanticSimilarity = $this->calculateSemanticSimilarity(
                    $post,
                    $interestEmbeddingIds
                );
                $authenticityScore = min(1.0, max(0.0, (float) $post->authenticity_score));
                $recencyScore = $this->calculateRecencyScore($post->created_at);

                $score =
                    self::WEIGHT_RELATIONSHIP * $relationshipDepth
                    + self::WEIGHT_SEMANTIC * $semanticSimilarity
                    + self::WEIGHT_AUTHENTICITY * $authenticityScore
                    + self::WEIGHT_RECENCY * $recencyScore;

                $post->feed_score = round($score, 6);

                return $post;
            })
            ->sortByDesc('feed_score')
            ->values();
    }

    /**
     * Semantic similarity proxy: posts whose embeddings appear in the viewer's
     * interest cluster receive higher scores. Falls back to a neutral baseline
     * when embedding data is unavailable.
     */
    private function calculateSemanticSimilarity(Post $post, array $interestEmbeddingIds): float
    {
        if ($post->embedding_id === null || $interestEmbeddingIds === []) {
            return 0.5;
        }

        $position = array_search($post->embedding_id, $interestEmbeddingIds, true);

        if ($position === false) {
            return 0.3;
        }

        // Earlier search results are more similar (rank-decay)
        return max(0.4, 1.0 - ($position * 0.12));
    }

    /**
     * Exponential time decay: newer posts score higher.
     * Uses a 72-hour half-life so content remains relevant for ~3 days.
     */
    private function calculateRecencyScore(?Carbon $createdAt): float
    {
        if ($createdAt === null) {
            return 0.0;
        }

        $ageHours = max(0, $createdAt->diffInMinutes(now()) / 60);

        return exp(-$ageHours / self::RECENCY_HALF_LIFE_HOURS);
    }
}
