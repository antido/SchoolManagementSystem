<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\RoleName;
use App\Enums\TeacherStatus;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    /**
     * Define the model's default state: an active teacher with a teacher account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'user_id' => User::factory()->withRole(RoleName::Teacher)->state([
                'name' => "{$firstName} {$lastName}",
            ]),
            'employee_number' => 'T-'.fake()->unique()->numerify('#####'),
            'first_name' => $firstName,
            'middle_name' => null,
            'last_name' => $lastName,
            'birth_date' => fake()->dateTimeBetween('-60 years', '-24 years')->format('Y-m-d'),
            'gender' => fake()->randomElement([Gender::Male, Gender::Female]),
            'contact_number' => fake()->numerify('09#########'),
            'specialization' => fake()->randomElement(['Mathematics', 'Science', 'English', 'Filipino', 'History']),
            'status' => TeacherStatus::Active,
        ];
    }

    public function withStatus(TeacherStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
