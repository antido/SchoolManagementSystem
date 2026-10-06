<?php

namespace App\Policies;

use App\Models\LearningActivity;
use App\Models\Question;
use App\Models\User;

/**
 * Questions, with their answer keys, are seen and changed only by the
 * people who manage the activity. They stop changing once students have
 * started answering them.
 */
class QuestionPolicy
{
    /**
     * See the questions of an activity, with the correct answers.
     */
    public function viewAny(User $user, LearningActivity $activity): bool
    {
        return $activity->type->usesQuestions()
            && $user->can('questions.view')
            && $activity->classSubject->isManagedBy($user);
    }

    public function create(User $user, LearningActivity $activity): bool
    {
        return $user->can('questions.create') && $this->canChange($user, $activity);
    }

    /**
     * Edit or reorder.
     */
    public function update(User $user, Question $question): bool
    {
        return $user->can('questions.update') && $this->canChange($user, $question->learningActivity);
    }

    public function delete(User $user, Question $question): bool
    {
        return $user->can('questions.delete') && $this->canChange($user, $question->learningActivity);
    }

    private function canChange(User $user, LearningActivity $activity): bool
    {
        return $activity->type->usesQuestions()
            && $activity->classSubject->isManagedBy($user)
            && ! $activity->questionsAreLocked();
    }
}
