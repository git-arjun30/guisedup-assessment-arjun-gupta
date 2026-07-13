<?php

namespace Tests\Feature;

use App\Models\Interaction;
use App\Models\Post;
use App\Models\User;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeedApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_ranked_feed(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();

        $post = Post::factory()->for($author)->create([
            'authenticity_score' => 0.85,
            'embedding_id' => 'embed-1',
        ]);

        Interaction::factory()->create([
            'user_id' => $viewer->id,
            'post_id' => $post->id,
            'type' => Interaction::TYPE_REACTION,
        ]);

        $this->mock(EmbeddingService::class, function ($mock) {
            $mock->shouldReceive('search')->andReturn([]);
        });

        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/feed');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
            ])
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('data.0.id', $post->id);

        $this->assertNotEmpty($response->json('data'));
    }
}
