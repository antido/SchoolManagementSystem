<?php

namespace Database\Factories;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\ClassSubject;
use App\Models\LearningActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningActivity>
 */
class LearningActivityFactory extends Factory
{
    /**
     * A draft assignment due in a week, created by the class subject's teacher.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_subject_id' => ClassSubject::factory(),
            'teacher_id' => fn (array $attributes) => ClassSubject::find($attributes['class_subject_id'])->teacher_id,
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'type' => ActivityType::Assignment,
            'instructions' => fake()->paragraph(),
            'start_at' => null,
            'due_at' => now()->addWeek(),
            'duration_minutes' => null,
            'total_points' => 100,
            'allow_late_submissions' => true,
            'max_attempts' => 1,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'status' => ActivityStatus::Draft,
        ];
    }

    public function ofType(ActivityType $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => ActivityStatus::Published]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => ActivityStatus::Closed]);
    }
}
