<?php

namespace App\Policies;

use App\Models\ClassTeacher;
use App\Models\User;

class ClassTeacherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('class_assignments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('class_assignments.create');
    }

    /**
     * End a current assignment.
     */
    public function end(User $user, ClassTeacher $assignment): bool
    {
        return $user->can('class_assignments.delete') && $assignment->isCurrent();
    }
}
