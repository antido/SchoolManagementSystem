<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    /**
     * A published question with no category.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'faq_category_id' => null,
            'question' => rtrim(fake()->sentence(), '.').'?',
            'answer' => fake()->paragraph(),
            'is_published' => true,
            'order' => fn (array $attributes) => (int) Faq::where('faq_category_id', $attributes['faq_category_id'])->max('order') + 1,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
