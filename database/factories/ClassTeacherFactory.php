<?php

namespace Database\Factories;

use App\Enums\ClassTeacherRole;
use App\Models\ClassTeacher;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassTeacher>
 */
class ClassTeacherFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_class_id' => SchoolClass::factory(),
            'teacher_id' => Teacher::factory(),
            'academic_year_id' => fn (array $attributes) => SchoolClass::find($attributes['school_class_id'])->academic_year_id,
            'role' => ClassTeacherRole::Teacher,
            'assigned_at' => now(),
            'ended_at' => null,
        ];
    }

    public function adviser(): static
    {
        return $this->state(fn () => ['role' => ClassTeacherRole::Adviser]);
    }

    public function ended(): static
    {
        return $this->state(fn () => ['ended_at' => now()]);
    }
}
