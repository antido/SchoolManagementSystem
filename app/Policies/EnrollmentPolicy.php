<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    /**
     * See class rosters and enrollment history.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('enrollments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('enrollments.create');
    }

    /**
     * Withdraw a student from a class. Only current enrollments can be withdrawn.
     */
    public function withdraw(User $user, Enrollment $enrollment): bool
    {
        return $user->can('enrollments.delete') && $enrollment->status === EnrollmentStatus::Active;
    }
}
