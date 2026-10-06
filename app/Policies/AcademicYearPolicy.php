<?php

namespace App\Policies;

use App\Models\AcademicYear;
use App\Models\User;

class AcademicYearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('academic_years.view');
    }

    public function view(User $user, AcademicYear $year): bool
    {
        return $user->can('academic_years.view');
    }

    public function create(User $user): bool
    {
        return $user->can('academic_years.create');
    }

    public function update(User $user, AcademicYear $year): bool
    {
        return $user->can('academic_years.update');
    }

    /**
     * Years with dependent records are kept for history and cannot be deleted.
     */
    public function delete(User $user, AcademicYear $year): bool
    {
        return $user->can('academic_years.delete') && ! $year->hasDependents();
    }
}
