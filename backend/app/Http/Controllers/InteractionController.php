<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInteractionRequest;
use App\Repositories\InteractionRepository;
use Illuminate\Http\JsonResponse;

class InteractionController extends Controller
{
    public function __construct(
        private readonly InteractionRepository $interactionRepository,
    ) {}

    public function store(StoreInteractionRequest $request): JsonResponse
    {
        $interaction = $this->interactionRepository->create([
            'user_id' => $request->user()->id,
            'post_id' => $request->validated('post_id'),
            'type' => $request->validated('type'),
        ]);

        return response()->json([
            'id' => $interaction->id,
            'user_id' => $interaction->user_id,
            'post_id' => $interaction->post_id,
            'type' => $interaction->type,
            'created_at' => $interaction->created_at?->toIso8601String(),
        ], 201);
    }
}
