<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Creates and updates students together with their sign-in accounts, so a
 * student never exists without an account (or the other way round).
 */
class StudentService
{
    public function __construct(private UserService $users) {}

    /**
     * @param  array<string, mixed>  $profile  Fields of the student profile.
     * @param  array{email: string, password: string}  $account
     */
    public function create(array $profile, array $account, StudentStatus $status = StudentStatus::Active): Student
    {
        return DB::transaction(function () use ($profile, $account, $status): Student {
            $student = new Student($profile);
            $student->status = $status;

            $user = $this->users->create([
                'name' => $student->full_name,
                'email' => $account['email'],
                'password' => $account['password'],
            ], RoleName::Student->value, $this->accountIsActive($status));

            $student->user()->associate($user);
            $student->save();

            return $student;
        });
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array{email: string, password?: string|null}  $account
     */
    public function update(Student $student, array $profile, array $account): Student
    {
        return DB::transaction(function () use ($student, $profile, $account): Student {
            $student->fill($profile)->save();

            // Keep the account's display name in step with the profile.
            $this->users->update($student->user, [
                'name' => $student->full_name,
                'email' => $account['email'],
                'password' => $account['password'] ?? null,
            ]);

            return $student;
        });
    }

    /**
     * Change the student's status. Students who leave the school (inactive,
     * graduated, transferred or dropped) can no longer sign in; making them
     * active again reopens their account.
     */
    public function changeStatus(Student $student, StudentStatus $status): void
    {
        DB::transaction(function () use ($student, $status): void {
            $student->status = $status;
            $student->save();

            $this->users->setActive($student->user, $this->accountIsActive($status));
        });
    }

    /**
     * Delete the student and their account.
     */
    public function delete(Student $student): void
    {
        DB::transaction(function () use ($student): void {
            $user = $student->user;
            $student->delete();
            $user->delete();
        });
    }

    private function accountIsActive(StudentStatus $status): bool
    {
        return $status === StudentStatus::Active;
    }
}
