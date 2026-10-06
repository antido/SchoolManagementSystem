<?php

namespace App\Policies;

use App\Models\ActivitySubmission;
use App\Models\User;

/**
 * Students see their own work. Teachers see and grade work for the class
 * subjects they teach; administrators for all of them.
 */
class ActivitySubmissionPolicy
{
    public function view(User $user, ActivitySubmission $submission): bool
    {
        if ($user->student !== null && $user->student->id === $submission->student_id) {
            return true;
        }

        return $user->can('submissions.view')
            && $submission->learningActivity->classSubject->isManagedBy($user);
    }

    public function grade(User $user, ActivitySubmission $submission): bool
    {
        return $user->can('submissions.grade')
            && $submission->learningActivity->classSubject->isManagedBy($user);
    }
}
