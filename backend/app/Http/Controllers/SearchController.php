<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Repositories\PostRepository;
use App\Services\EmbeddingService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\SearchRequest;

class SearchController extends Controller
{
    private const RESULT_LIMIT = 10;

    public function __construct(
        private readonly EmbeddingService $embeddingService,
        private readonly PostRepository $postRepository,
    ) {}

    public function index(SearchRequest $request): JsonResponse
    {
        $query = $request->validated('q');

        $embeddingIds = $this->embeddingService->search($query, self::RESULT_LIMIT);
        $posts = $this->postRepository->findByEmbeddingIds($embeddingIds, self::RESULT_LIMIT);

        // Preserve ChromaDB relevance order
        $ordered = collect($embeddingIds)
            ->map(fn (string $id) => $posts->firstWhere('embedding_id', $id))
            ->filter()
            ->values();

        return response()->json([
            // Resolve before manual JSON construction to keep `data` a flat array.
            'data' => PostResource::collection($ordered)->resolve(),
        ]);
    }
}
