<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Models\Award;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Award>
 */
class AwardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Academic Excellence', 'Perfect Attendance', 'Top Performer', 'Most Improved', 'Outstanding Student'])
                .' '.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->sentence(),
            'criteria' => fake()->sentence(),
            'status' => ActiveStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => ActiveStatus::Inactive]);
    }
}
