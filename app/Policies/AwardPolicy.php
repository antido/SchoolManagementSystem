<?php

namespace App\Policies;

use App\Models\Award;
use App\Models\Student;
use App\Models\StudentAward;
use App\Models\User;

/**
 * Administrators manage the list of awards. Teachers may give awards to
 * students in their own classes; administrators to any student.
 */
class AwardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('awards.view');
    }

    public function view(User $user, Award $award): bool
    {
        return $user->can('awards.view');
    }

    public function create(User $user): bool
    {
        return $user->can('awards.create');
    }

    public function update(User $user, Award $award): bool
    {
        return $user->can('awards.update');
    }

    /**
     * Awards already given to someone are made inactive instead.
     */
    public function delete(User $user, Award $award): bool
    {
        return $user->can('awards.delete') && ! $award->hasDependents();
    }

    /**
     * Give the award, to a particular student when one is given.
     */
    public function grant(User $user, Award $award, ?Student $student = null): bool
    {
        if (! $user->can('awards.grant') || ! $award->isActive() || $user->student !== null) {
            return false;
        }

        return $student === null || Student::query()->visibleTo($user)->whereKey($student->id)->exists();
    }

    /**
     * Take back an award: administrators, or the teacher who gave it.
     */
    public function revoke(User $user, StudentAward $studentAward): bool
    {
        if (! $user->can('awards.grant') || $studentAward->isRevoked()) {
            return false;
        }

        return ! $user->isLimitedToOwnClasses() || $studentAward->awarded_by === $user->id;
    }
}
