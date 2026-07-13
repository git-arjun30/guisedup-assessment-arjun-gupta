<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_search_posts_in_vector_relevance_order(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $first = Post::factory()->for($author)->create(['embedding_id' => 'vector-first']);
        $second = Post::factory()->for($author)->create(['embedding_id' => 'vector-second']);

        $this->mock(EmbeddingService::class, function ($mock) {
            $mock->shouldReceive('search')->once()->with('travel stories', 10)
                ->andReturn(['vector-second', 'vector-first']);
        });

        Sanctum::actingAs($viewer);

        $this->getJson('/api/search?q=travel%20stories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id);
    }

    public function test_search_requires_a_query(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/search')->assertUnprocessable();
    }
}
