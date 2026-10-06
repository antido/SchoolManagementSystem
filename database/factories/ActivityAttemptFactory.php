<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Enums\AttemptStatus;
use App\Models\ActivityAttempt;
use App\Models\LearningActivity;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityAttempt>
 */
class ActivityAttemptFactory extends Factory
{
    /**
     * A graded attempt at a published quiz. Prefer AttemptService::start()
     * in tests that answer questions.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_activity_id' => LearningActivity::factory()->ofType(ActivityType::Quiz)->published(),
            'student_id' => Student::factory(),
            'attempt_number' => 1,
            'status' => AttemptStatus::Graded,
            'started_at' => now()->subHour(),
            'expires_at' => null,
            'submitted_at' => now()->subMinutes(30),
            'auto_submitted' => false,
            'score' => 0,
            'question_order' => [],
            'option_order' => [],
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => AttemptStatus::InProgress, 'submitted_at' => null, 'score' => null]);
    }
}
