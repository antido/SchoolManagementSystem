<?php

namespace App\Enums;

/**
 * The stored status of a student's submission. What a student or teacher
 * sees also covers work not handed in yet ("pending" or "missing"), which
 * has no row; see ActivitySubmission::displayStatus().
 */
enum SubmissionStatus: string
{
    /** Handed in and waiting for the teacher. */
    case Submitted = 'submitted';

    /** Scored by the teacher. The student can no longer change it. */
    case Graded = 'graded';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
