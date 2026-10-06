<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicTerm>
 */
class AcademicTermFactory extends Factory
{
    /**
     * By default the term covers its whole year.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'name' => 'Term '.fake()->unique()->numberBetween(1, 99999),
            'start_date' => fn (array $attributes) => AcademicYear::find($attributes['academic_year_id'])->start_date,
            'end_date' => fn (array $attributes) => AcademicYear::find($attributes['academic_year_id'])->end_date,
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }
}
