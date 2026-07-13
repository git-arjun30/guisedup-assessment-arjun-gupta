<?php

namespace Tests\Feature;

use App\Models\Interaction;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InteractionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_record_interaction(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/interactions', [
            'post_id' => $post->id,
            'type' => Interaction::TYPE_VIEW,
        ]);

        $response->assertCreated()
            ->assertJsonPath('post_id', $post->id)
            ->assertJsonPath('type', Interaction::TYPE_VIEW);

        $this->assertDatabaseHas('interactions', [
            'user_id' => $user->id,
            'post_id' => $post->id,
            'type' => Interaction::TYPE_VIEW,
        ]);
    }

    public function test_interaction_requires_valid_type(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/interactions', [
            'post_id' => $post->id,
            'type' => 'invalid',
        ]);

        $response->assertUnprocessable();
    }
}
