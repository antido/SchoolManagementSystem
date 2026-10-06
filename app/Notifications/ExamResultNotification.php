<?php

namespace App\Notifications;

use App\Models\ActivityAttempt;

/**
 * To the student: the result of a quiz, examination or questionnaire
 * attempt is ready.
 */
class ExamResultNotification extends SchoolNotification
{
    /**
     * Load `learningActivity` first.
     */
    public function __construct(private readonly ActivityAttempt $attempt) {}

    protected function title(): string
    {
        return "Your result for {$this->attempt->learningActivity->title} is ready";
    }

    protected function body(): ?string
    {
        return 'Score: '.(float) $this->attempt->score.' / '.(float) $this->attempt->learningActivity->total_points;
    }

    protected function url(): string
    {
        return route('attempts.show', $this->attempt, false);
    }

    protected function icon(): string
    {
        return 'exam';
    }
}
