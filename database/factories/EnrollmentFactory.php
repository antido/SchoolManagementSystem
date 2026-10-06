<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_class_id' => SchoolClass::factory(),
            'student_id' => Student::factory(),
            'academic_year_id' => fn (array $attributes) => SchoolClass::find($attributes['school_class_id'])->academic_year_id,
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Active,
        ];
    }

    public function withdrawn(): static
    {
        return $this->state(fn () => ['status' => EnrollmentStatus::Withdrawn, 'ended_at' => now()]);
    }
}
