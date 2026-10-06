<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\ActivitySubmission;
use App\Models\LearningActivity;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivitySubmission>
 */
class ActivitySubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_activity_id' => LearningActivity::factory()->published(),
            'student_id' => Student::factory(),
            'body' => fake()->paragraph(),
            'status' => SubmissionStatus::Submitted,
            'submitted_at' => now(),
            'is_late' => false,
        ];
    }

    public function late(): static
    {
        return $this->state(fn () => ['is_late' => true]);
    }

    public function graded(float $score = 80): static
    {
        return $this->state(fn () => [
            'status' => SubmissionStatus::Graded,
            'score' => $score,
            'feedback' => 'Good work.',
            'graded_at' => now(),
        ]);
    }
}
