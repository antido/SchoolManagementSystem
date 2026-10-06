<?php

namespace App\Notifications;

use App\Models\LearningActivity;

/**
 * To students: a new assignment, quiz, examination or other activity was
 * published for one of their classes.
 */
class ActivityPublishedNotification extends SchoolNotification
{
    /**
     * Load `classSubject.subject` first.
     */
    public function __construct(private readonly LearningActivity $activity) {}

    protected function title(): string
    {
        return "New {$this->activity->type->value}: {$this->activity->title}";
    }

    protected function body(): ?string
    {
        $due = $this->activity->due_at ? ', due '.$this->activity->due_at->format('M j, Y g:i A') : '';

        return $this->activity->classSubject->subject->name.$due;
    }

    protected function url(): string
    {
        return route('activities.show', $this->activity, false);
    }

    protected function icon(): string
    {
        return $this->activity->type->usesQuestions() ? 'exam' : 'clipboard';
    }
}
