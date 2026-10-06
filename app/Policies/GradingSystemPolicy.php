<?php

namespace App\Policies;

use App\Models\GradingScale;
use App\Models\GradingSystem;
use App\Models\User;

/**
 * Grading systems and scales are school-wide settings. They are managed by
 * staff with grades.manage who are not limited to their own classes, which
 * in practice means administrators. Teachers choose among them for the
 * class subjects they teach.
 */
class GradingSystemPolicy
{
    public function manage(User $user): bool
    {
        return $user->can('grades.manage') && ! $user->isLimitedToOwnClasses();
    }

    /**
     * Systems in use by a class subject, or the default one, are kept.
     */
    public function delete(User $user, GradingSystem $system): bool
    {
        return $this->manage($user) && ! $system->is_default && ! $system->classSubjects()->exists();
    }

    /**
     * Scales used by a grading system, or the default one, are kept.
     */
    public function deleteScale(User $user, GradingScale $scale): bool
    {
        return $this->manage($user) && ! $scale->is_default && ! $scale->systems()->exists();
    }
}
