<?php

namespace App\Notifications;

use App\Models\ActivitySubmission;

/**
 * To the student: their handed-in work was graded.
 */
class WorkGradedNotification extends SchoolNotification
{
    /**
     * Load `learningActivity` first.
     */
    public function __construct(private readonly ActivitySubmission $submission) {}

    protected function title(): string
    {
        return "{$this->submission->learningActivity->title} was graded";
    }

    protected function body(): ?string
    {
        return 'Score: '.(float) $this->submission->score.' / '.(float) $this->submission->learningActivity->total_points;
    }

    protected function url(): string
    {
        return route('activities.show', $this->submission->learning_activity_id, false);
    }

    protected function icon(): string
    {
        return 'check';
    }
}
