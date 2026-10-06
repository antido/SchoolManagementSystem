<?php

namespace Database\Factories;

use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumTopic>
 */
class ForumTopicFactory extends Factory
{
    /**
     * A topic with its opening post, by the same author.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'forum_category_id' => ForumCategory::factory(),
            'user_id' => User::factory(),
            'title' => rtrim(fake()->sentence(5), '.'),
            'is_pinned' => false,
            'is_locked' => false,
            'replies_count' => 0,
            'last_posted_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ForumTopic $topic): void {
            ForumPost::factory()->create([
                'forum_topic_id' => $topic->id,
                'user_id' => $topic->user_id,
                'created_at' => $topic->created_at,
            ]);
        });
    }

    public function locked(): static
    {
        return $this->state(fn () => ['is_locked' => true]);
    }
}
