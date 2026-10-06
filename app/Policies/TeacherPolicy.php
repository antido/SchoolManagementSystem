<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;

/**
 * Teachers are managed by administrators and HR (both hold the teachers.*
 * permissions). A teacher may open their own profile.
 */
class TeacherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('teachers.view');
    }

    public function view(User $user, Teacher $teacher): bool
    {
        return $user->can('teachers.view') || $user->id === $teacher->user_id;
    }

    public function create(User $user): bool
    {
        return $user->can('teachers.create');
    }

    public function update(User $user, Teacher $teacher): bool
    {
        return $user->can('teachers.update');
    }

    /**
     * Teachers who have taught or been assigned to a class are kept for
     * history; change their status instead.
     */
    public function delete(User $user, Teacher $teacher): bool
    {
        return $user->can('teachers.delete') && ! $teacher->hasHistory();
    }
}
