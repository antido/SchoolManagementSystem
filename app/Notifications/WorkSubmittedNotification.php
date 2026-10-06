<?php

namespace App\Notifications;

use App\Models\ActivitySubmission;

/**
 * To the teacher: a student handed in work.
 */
class WorkSubmittedNotification extends SchoolNotification
{
    /**
     * Load `student` and `learningActivity` first.
     */
    public function __construct(private readonly ActivitySubmission $submission) {}

    protected function title(): string
    {
        return "{$this->submission->student->full_name} handed in {$this->submission->learningActivity->title}";
    }

    protected function body(): ?string
    {
        return $this->submission->is_late ? 'Handed in after the due date.' : null;
    }

    protected function url(): string
    {
        return route('submissions.show', $this->submission, false);
    }

    protected function icon(): string
    {
        return 'clipboard';
    }
}
