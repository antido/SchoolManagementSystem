<?php

namespace App\Policies;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\SubmissionStatus;
use App\Models\ClassSubject;
use App\Models\LearningActivity;
use App\Models\User;

/**
 * Who may see and manage learning activities.
 *
 * Quizzes and examinations use the examinations.* permissions; the other
 * kinds use assignments.* (see ActivityType::permissionArea). On top of the
 * permission, teachers may only manage activities of the class subjects
 * they teach, and students only see what is available to them
 * (see LearningActivity::scopeVisibleTo).
 */
class LearningActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return ActivityType::allowedFor($user, 'view') !== [];
    }

    public function view(User $user, LearningActivity $activity): bool
    {
        return $user->can($activity->type->permission('view')) && $activity->isVisibleTo($user);
    }

    /**
     * Create an activity of this kind for this class subject.
     */
    public function create(User $user, ClassSubject $classSubject, ActivityType $type): bool
    {
        return $user->can($type->permission('create'))
            && $classSubject->isActive()
            && $classSubject->isManagedBy($user);
    }

    /**
     * Edit, publish, close and reopen.
     */
    public function update(User $user, LearningActivity $activity): bool
    {
        return $user->can($activity->type->permission('update'))
            && $activity->classSubject->isManagedBy($user);
    }

    /**
     * Activities with student work are closed, not deleted.
     */
    public function delete(User $user, LearningActivity $activity): bool
    {
        return $user->can($activity->type->permission('delete'))
            && $activity->classSubject->isManagedBy($user)
            && ! $activity->hasDependents();
    }

    /**
     * Hand in work: the person is a student who can see the activity, it is
     * accepting work, and their earlier work (if any) has not been graded.
     */
    public function submit(User $user, LearningActivity $activity): bool
    {
        if ($user->student === null || ! $user->can($activity->type->permission('submit'))) {
            return false;
        }

        return $activity->isAcceptingSubmissions()
            && $activity->isVisibleTo($user)
            && ! $activity->submissions()
                ->where('student_id', $user->student->id)
                ->where('status', SubmissionStatus::Graded)
                ->exists();
    }

    /**
     * Start or resume an attempt at a quiz, examination or questionnaire:
     * the person is a student who can see it, it is open, it has questions,
     * and they have an attempt in progress or attempts left.
     */
    public function attempt(User $user, LearningActivity $activity): bool
    {
        if ($user->student === null || ! $user->can($activity->type->permission($activity->type->studentAction()))) {
            return false;
        }

        if (! $activity->isAcceptingAttempts() || ! $activity->isVisibleTo($user) || ! $activity->questions()->exists()) {
            return false;
        }

        $attempts = $activity->attempts()->where('student_id', $user->student->id);

        return (clone $attempts)->inProgress()->exists() || $attempts->count() < $activity->max_attempts;
    }

    /**
     * Change its status from the current one to the given one.
     */
    public function changeStatus(User $user, LearningActivity $activity, ActivityStatus $status): bool
    {
        return $this->update($user, $activity) && $activity->status->canChangeTo($status);
    }
}
