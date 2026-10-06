<?php

namespace Database\Factories;

use App\Models\Award;
use App\Models\Student;
use App\Models\StudentAward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentAward>
 */
class StudentAwardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'award_id' => Award::factory(),
            'student_id' => Student::factory(),
            'academic_year_id' => null,
            'school_class_id' => null,
            'awarded_on' => today(),
            'remarks' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now(), 'revoked_reason' => 'Given by mistake.']);
    }
}
