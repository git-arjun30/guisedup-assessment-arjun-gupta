<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_post(): void
    {
        $user = User::factory()->create();

        $this->mock(EmbeddingService::class, function ($mock) {
            $mock->shouldReceive('embed')
                ->once()
                ->with('Trip to Goa')
                ->andReturn('abc123');
        });

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/posts', [
            'content' => 'Trip to Goa',
            'image_url' => 'https://example.com/image.jpg',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.content', 'Trip to Goa')
            ->assertJsonPath('data.embedding_id', 'abc123');

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'content' => 'Trip to Goa',
            'embedding_id' => 'abc123',
        ]);
    }

    public function test_unauthenticated_user_cannot_create_post(): void
    {
        $response = $this->postJson('/api/posts', [
            'content' => 'Trip to Goa',
        ]);

        $response->assertUnauthorized();
    }
}
