<?php

namespace App\Policies;

use App\Models\AcademicTerm;
use App\Models\User;

class AcademicTermPolicy
{
    public function create(User $user): bool
    {
        return $user->can('academic_terms.create');
    }

    public function update(User $user, AcademicTerm $term): bool
    {
        return $user->can('academic_terms.update');
    }

    /**
     * Terms with dependent records are kept for history and cannot be deleted.
     */
    public function delete(User $user, AcademicTerm $term): bool
    {
        return $user->can('academic_terms.delete') && ! $term->hasDependents();
    }
}
