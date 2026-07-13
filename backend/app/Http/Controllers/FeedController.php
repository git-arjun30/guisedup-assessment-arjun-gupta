<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Repositories\PostRepository;
use App\Services\FeedRankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly FeedRankingService $feedRankingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));

        $candidates = $this->postRepository->getFeedCandidates($request->user()->id);
        $ranked = $this->feedRankingService->rank($request->user(), $candidates);
        $paginator = $this->postRepository->paginateRanked($ranked, self::PER_PAGE, $page);

        return response()->json([
            // Resolve before manual JSON construction to keep `data` a flat array.
            'data' => PostResource::collection($paginator->items())->resolve(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }
}
