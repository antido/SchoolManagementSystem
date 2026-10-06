<?php

namespace Database\Factories;

use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumPost>
 */
class ForumPostFactory extends Factory
{
    /**
     * A reply in a topic. Topics already come with their opening post.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'forum_topic_id' => ForumTopic::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'created_at' => now()->addSecond(),
        ];
    }
}
