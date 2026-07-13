<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    private readonly string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = $baseUrl ?: config('services.embedding.url', 'http://127.0.0.1:5000');
    }

    /**
     * Generate and persist a vector embedding for post content.
     */
    public function embed(string $text): ?string
    {
        try {
            $response = Http::timeout(30)
                ->post("{$this->baseUrl}/embed", ['text' => $text]);

            if ($response->successful()) {
                return $response->json('embedding_id');
            }

            Log::warning('Embedding service embed failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Embedding service embed error', [
                'message' => $exception->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Perform semantic search and return matching embedding IDs ordered by relevance.
     *
     * @return array<int, string>
     */
    public function search(string $query, int $limit = 10): array
    {
        try {
            $response = Http::timeout(30)
                ->post("{$this->baseUrl}/search", [
                    'query' => $query,
                    'limit' => $limit,
                ]);

            if ($response->successful()) {
                return $response->json('embedding_ids', []);
            }

            Log::warning('Embedding service search failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Embedding service search error', [
                'message' => $exception->getMessage(),
            ]);
        }

        return [];
    }
}
