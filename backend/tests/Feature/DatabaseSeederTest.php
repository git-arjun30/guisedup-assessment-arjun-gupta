<?php

namespace Tests\Feature;

use App\Services\EmbeddingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_seed_data_has_exactly_fifty_posts_and_two_hundred_interactions(): void
    {
        $this->mock(EmbeddingService::class, function ($mock) {
            $mock->shouldReceive('embed')->times(50)->andReturn(null);
        });

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 10);
        $this->assertDatabaseCount('posts', 50);
        $this->assertDatabaseCount('interactions', 200);
    }
}
