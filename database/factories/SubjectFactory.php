<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Mathematics', 'Science', 'English', 'Filipino', 'Araling Panlipunan', 'MAPEH']);

        return [
            'code' => strtoupper(substr($name, 0, 4)).'-'.fake()->unique()->numerify('###'),
            'name' => $name,
            'description' => null,
            'status' => ActiveStatus::Active,
        ];
    }
}
