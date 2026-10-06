<?php

namespace App\Policies;

use App\Models\ActivityAttempt;
use App\Models\User;

/**
 * Students see and answer their own attempts. Teachers see and score the
 * attempts for the class subjects they teach; administrators for all.
 */
class ActivityAttemptPolicy
{
    public function view(User $user, ActivityAttempt $attempt): bool
    {
        return $this->owns($user, $attempt) || $this->manages($user, $attempt, 'submissions.view');
    }

    /**
     * Save answers and hand in. Whether time is left is checked by AttemptService.
     */
    public function answer(User $user, ActivityAttempt $attempt): bool
    {
        return $this->owns($user, $attempt) && $attempt->isInProgress();
    }

    /**
     * Score written answers once the attempt has been handed in.
     */
    public function grade(User $user, ActivityAttempt $attempt): bool
    {
        return ! $attempt->isInProgress() && $this->manages($user, $attempt, 'submissions.grade');
    }

    private function owns(User $user, ActivityAttempt $attempt): bool
    {
        return $user->student !== null && $user->student->id === $attempt->student_id;
    }

    private function manages(User $user, ActivityAttempt $attempt, string $permission): bool
    {
        return $user->can($permission) && $attempt->learningActivity->classSubject->isManagedBy($user);
    }
}
