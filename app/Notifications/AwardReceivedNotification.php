<?php

namespace App\Notifications;

use App\Models\StudentAward;

/**
 * To the student: they received an award.
 */
class AwardReceivedNotification extends SchoolNotification
{
    /**
     * Load `award` first.
     */
    public function __construct(private readonly StudentAward $studentAward) {}

    protected function title(): string
    {
        return "You received {$this->studentAward->award->name}";
    }

    protected function body(): ?string
    {
        return $this->studentAward->remarks;
    }

    protected function url(): string
    {
        return route('awards.show', $this->studentAward->award_id, false);
    }

    protected function icon(): string
    {
        return 'award';
    }
}
