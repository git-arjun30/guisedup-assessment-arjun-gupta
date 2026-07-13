<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    protected $model = Post::class;

    private const SAMPLE_CONTENT = [
        'Just got back from an amazing trip to Goa! The beaches were incredible and the sunset views were unforgettable.',
        'Spent the weekend hiking in the mountains. Nothing beats fresh air and good company on the trail.',
        'Tried a new coffee shop downtown — their pour-over is absolutely worth the hype.',
        'Book recommendation: finally finished that novel everyone has been talking about. No spoilers, but wow.',
        'Sunday brunch with friends turned into a four-hour conversation. These are the moments that matter.',
        'Started learning guitar again after years. Fingers hurt but progress feels great.',
        'Random thought: the best travel stories always involve something going slightly wrong.',
        'Cooking experiment tonight — homemade pasta from scratch. Wish me luck!',
        'Caught the most beautiful sunrise on my morning run. Worth waking up early for once.',
        'Reflecting on how much has changed in the past year. Grateful for the journey.',
        'Movie night recommendation: watched an indie film that completely surprised me.',
        'Farmers market haul — fresh tomatoes, basil, and bread for the perfect caprese.',
        'Deep work session today. Turned off notifications and actually finished a project.',
        'Funny travel story: missed my train in Prague and ended up at the best hidden cafe.',
        'Pet update: my cat learned to open doors. Privacy is officially a thing of the past.',
        'Concert last night was electric. Live music hits different after so long indoors.',
        'Working remotely from a cabin this week. Productivity and peace in equal measure.',
        'Shared a meal with strangers at a hostel and made friends for life. Travel magic.',
        'Photography walk through the old town — every corner tells a story.',
        'Rainy day vibes: tea, a blanket, and rewatching a comfort show.',
    ];

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'content' => fake()->randomElement(self::SAMPLE_CONTENT),
            'image_url' => fake()->boolean(40)
                ? 'https://picsum.photos/seed/'.fake()->uuid().'/800/600'
                : null,
            'embedding_id' => null,
            'authenticity_score' => fake()->randomFloat(4, 0.3, 0.95),
        ];
    }
}
