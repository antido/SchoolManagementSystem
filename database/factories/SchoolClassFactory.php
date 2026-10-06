<?php

namespace Database\Factories;

use App\Enums\ClassStatus;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grade = fake()->numberBetween(7, 10);
        $section = fake()->randomElement(['Rizal', 'Mabini', 'Bonifacio', 'Luna', 'Silang']);

        return [
            'academic_year_id' => AcademicYear::factory(),
            'name' => "Grade {$grade} - {$section}",
            'code' => 'G'.$grade.'-'.fake()->unique()->bothify('???##'),
            'grade_level' => "Grade {$grade}",
            'section' => $section,
            'description' => null,
            'status' => ClassStatus::Active,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => ClassStatus::Archived]);
    }
}
