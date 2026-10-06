<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /**
     * See the student list. Teachers' lists are limited to their own
     * students (see Student::scopeVisibleTo).
     */
    public function viewAny(User $user): bool
    {
        return $user->can('students.view');
    }

    /**
     * See one student's profile: staff with students.view (teachers only for
     * students in their classes), or the student themselves.
     */
    public function view(User $user, Student $student): bool
    {
        if ($user->id === $student->user_id) {
            return true;
        }

        return $user->can('students.view')
            && Student::query()->visibleTo($user)->whereKey($student->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('students.create');
    }

    /**
     * Edit the profile and change the status. Students cannot edit their own
     * administrative record; they change their password on the profile page.
     */
    public function update(User $user, Student $student): bool
    {
        return $user->can('students.update');
    }

    /**
     * Students with any school history are kept; change their status instead.
     */
    public function delete(User $user, Student $student): bool
    {
        return $user->can('students.delete') && ! $student->hasHistory();
    }
}
