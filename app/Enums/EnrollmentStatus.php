<?php

namespace App\Enums;

/**
 * The state of a student's place in a class. Enrollments are never deleted:
 * a student who leaves a class is marked withdrawn, so history is kept.
 */
enum EnrollmentStatus: string
{
    /** Currently in the class. */
    case Active = 'active';

    /** Removed from the class before the year ended. */
    case Withdrawn = 'withdrawn';

    /** Finished the class at the end of the year. */
    case Completed = 'completed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
