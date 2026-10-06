<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state: an active student with a student account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'user_id' => User::factory()->withRole(RoleName::Student)->state([
                'name' => "{$firstName} {$lastName}",
            ]),
            'student_number' => 'S-'.fake()->unique()->numerify('######'),
            'first_name' => $firstName,
            'middle_name' => null,
            'last_name' => $lastName,
            'birth_date' => fake()->dateTimeBetween('-18 years', '-11 years')->format('Y-m-d'),
            'gender' => fake()->randomElement([Gender::Male, Gender::Female]),
            'address' => fake()->address(),
            'contact_number' => fake()->numerify('09#########'),
            'status' => StudentStatus::Active,
        ];
    }

    public function withStatus(StudentStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
