<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\TeacherStatus;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;

/**
 * Creates and updates teachers together with their sign-in accounts, so a
 * teacher never exists without an account (or the other way round).
 */
class TeacherService
{
    public function __construct(private UserService $users) {}

    /**
     * @param  array<string, mixed>  $profile
     * @param  array{email: string, password: string}  $account
     */
    public function create(array $profile, array $account, TeacherStatus $status = TeacherStatus::Active): Teacher
    {
        return DB::transaction(function () use ($profile, $account, $status): Teacher {
            $teacher = new Teacher($profile);
            $teacher->status = $status;

            $user = $this->users->create([
                'name' => $teacher->full_name,
                'email' => $account['email'],
                'password' => $account['password'],
            ], RoleName::Teacher->value, $status->canSignIn());

            $teacher->user()->associate($user);
            $teacher->save();

            return $teacher;
        });
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array{email: string, password?: string|null}  $account
     */
    public function update(Teacher $teacher, array $profile, array $account): Teacher
    {
        return DB::transaction(function () use ($teacher, $profile, $account): Teacher {
            $teacher->fill($profile)->save();

            $this->users->update($teacher->user, [
                'name' => $teacher->full_name,
                'email' => $account['email'],
                'password' => $account['password'] ?? null,
            ]);

            return $teacher;
        });
    }

    /**
     * Change the teacher's status and open or close their sign-in to match.
     */
    public function changeStatus(Teacher $teacher, TeacherStatus $status): void
    {
        DB::transaction(function () use ($teacher, $status): void {
            $teacher->status = $status;
            $teacher->save();

            $this->users->setActive($teacher->user, $status->canSignIn());
        });
    }

    /**
     * Delete the teacher and their account.
     */
    public function delete(Teacher $teacher): void
    {
        DB::transaction(function () use ($teacher): void {
            $user = $teacher->user;
            $teacher->delete();
            $user->delete();
        });
    }
}
