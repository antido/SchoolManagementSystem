<?php

namespace App\Enums;

/**
 * Where a student's attempt at a quiz, examination or questionnaire is.
 */
enum AttemptStatus: string
{
    /** Started and still being answered. */
    case InProgress = 'in_progress';

    /** Handed in (or closed when time ran out); some answers await the teacher. */
    case Submitted = 'submitted';

    /** Every answer has been scored. */
    case Graded = 'graded';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In progress',
            self::Submitted => 'Submitted',
            self::Graded => 'Graded',
        };
    }
}
