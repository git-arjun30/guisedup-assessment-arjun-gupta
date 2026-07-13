<?php

namespace Database\Seeders;

use App\Models\Interaction;
use App\Models\Post;
use App\Models\User;
use App\Services\EmbeddingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $alice = User::query()->updateOrCreate(
            ['email' => 'alice@example.com'],
            [
                'name' => 'Alice Johnson',
                'password' => Hash::make('password'),
            ]
        );

        $bob = User::query()->updateOrCreate(
            ['email' => 'bob@example.com'],
            [
                'name' => 'Bob Smith',
                'password' => Hash::make('password'),
            ]
        );

        $users = User::factory()->count(8)->create();
        $allUsers = collect([$alice, $bob])->merge($users);

        $embeddingService = app(EmbeddingService::class);

        // Exactly 50 posts are required. Rotate authors so the feed remains diverse.
        $posts = collect();
        for ($i = 0; $i < 50; $i++) {
            $user = $allUsers->random();
            $post = Post::factory()->for($user)->create();
            $embeddingId = $embeddingService->embed($post->content);
            if ($embeddingId) {
                $post->update(['embedding_id' => $embeddingId]);
            }
            $posts->push($post);
        }

        // Create 200 interactions distributed across posts
        for ($i = 0; $i < 200; $i++) {
            Interaction::factory()->create([
                'user_id' => $allUsers->random()->id,
                'post_id' => $posts->random()->id,
                'type' => fake()->randomElement([
                    Interaction::TYPE_VIEW,
                    Interaction::TYPE_VIEW,
                    Interaction::TYPE_VIEW,
                    Interaction::TYPE_REACTION,
                    Interaction::TYPE_REPLY,
                ]),
            ]);
        }
    }
}
