<?php

namespace App\Policies;

use App\Models\ClassSubject;
use App\Models\User;

/**
 * Assigning subjects (and their teachers) to classes is part of subject
 * management, so it uses the subjects.* permissions. Seeing a class subject
 * also needs access to its class.
 */
class ClassSubjectPolicy
{
    public function view(User $user, ClassSubject $classSubject): bool
    {
        return $user->can('subjects.view') && $classSubject->schoolClass->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('subjects.create');
    }

    public function update(User $user, ClassSubject $classSubject): bool
    {
        return $user->can('subjects.update');
    }

    /**
     * See every student's grade in the class subject.
     */
    public function viewGrades(User $user, ClassSubject $classSubject): bool
    {
        return $user->can('grades.view') && $user->student === null && $classSubject->isManagedBy($user);
    }

    /**
     * Compute grades and choose the grading system.
     */
    public function manageGrades(User $user, ClassSubject $classSubject): bool
    {
        return $user->can('grades.manage') && $classSubject->isManagedBy($user);
    }

    public function delete(User $user, ClassSubject $classSubject): bool
    {
        return $user->can('subjects.delete') && ! $classSubject->hasDependents();
    }
}
