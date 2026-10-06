<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Models\AcademicTerm;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSubject>
 */
class ClassSubjectFactory extends Factory
{
    /**
     * A class subject whose term belongs to the class's academic year.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_class_id' => SchoolClass::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => Teacher::factory(),
            'academic_term_id' => fn (array $attributes) => AcademicTerm::factory()->create([
                'academic_year_id' => SchoolClass::find($attributes['school_class_id'])->academic_year_id,
            ])->id,
            'status' => ActiveStatus::Active,
        ];
    }

    /**
     * Indicate that no teacher is assigned yet.
     */
    public function withoutTeacher(): static
    {
        return $this->state(fn () => ['teacher_id' => null]);
    }
}
