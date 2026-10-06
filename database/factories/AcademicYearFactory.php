<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    /**
     * Each new year starts a year after the previous one, so years made by
     * the factory never overlap.
     */
    private static int $offset = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = 2000 + self::$offset++;

        return [
            'name' => "{$startYear}-".($startYear + 1),
            'start_date' => "{$startYear}-06-01",
            'end_date' => ($startYear + 1).'-03-31',
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }
}
