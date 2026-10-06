<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

/**
 * Who may see and manage classes.
 *
 * Teachers see only the classes they are assigned to or teach; students see
 * only the classes they are enrolled in (see SchoolClass::scopeVisibleTo).
 */
class SchoolClassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('classes.view');
    }

    public function view(User $user, SchoolClass $class): bool
    {
        return $user->can('classes.view') && $class->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('classes.create');
    }

    public function update(User $user, SchoolClass $class): bool
    {
        return $user->can('classes.update');
    }

    /**
     * Classes with students, teachers or subjects are archived, not deleted.
     */
    public function delete(User $user, SchoolClass $class): bool
    {
        return $user->can('classes.delete') && ! $class->hasDependents();
    }
}
