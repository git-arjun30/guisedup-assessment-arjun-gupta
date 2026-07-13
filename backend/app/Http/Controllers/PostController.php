<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;
use App\Repositories\PostRepository;
use App\Services\EmbeddingService;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly EmbeddingService $embeddingService,
    ) {}

    public function store(StorePostRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $post = $this->postRepository->create([
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
            'image_url' => $validated['image_url'] ?? null,
            'embedding_id' => null,
            'authenticity_score' => $this->calculateAuthenticityScore($validated['content']),
        ]);

        // A post remains valid if the local vector service is temporarily unavailable.
        // Persist first so the embedding id can be attached without losing user content.
        $embeddingId = $this->embeddingService->embed($post->content);
        if ($embeddingId !== null) {
            $post->update(['embedding_id' => $embeddingId]);
        }

        $post->load('user');

        return (new PostResource($post))->response()->setStatusCode(201);
    }

    /**
     * Lightweight heuristic authenticity score based on content characteristics.
     * Longer, descriptive posts score higher; spam-like patterns score lower.
     */
    private function calculateAuthenticityScore(string $content): float
    {
        $length = strlen(trim($content));
        $wordCount = str_word_count($content);

        $lengthScore = min(1.0, $length / 280);
        $wordScore = min(1.0, $wordCount / 40);

        return round(($lengthScore * 0.6) + ($wordScore * 0.4), 4);
    }
}
