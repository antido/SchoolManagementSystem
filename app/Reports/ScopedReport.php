<?php

namespace App\Reports;

use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;

/**
 * A report about school records. Staff need reports.view plus the
 * permission for the kind of data; teachers are further limited to their
 * own classes, students and subjects, exactly as on the regular pages.
 */
abstract class ScopedReport extends Report
{
    /**
     * The permission for the data itself, e.g. "grades.view".
     */
    abstract protected function dataPermission(): string;

    public function allows(User $user): bool
    {
        return $user->student === null && $user->can('reports.view') && $user->can($this->dataPermission());
    }

    public function allowsFilters(User $user, ReportFilters $filters): bool
    {
        return (! $filters->student || $this->canSeeStudent($user, $filters->student))
            && (! $filters->schoolClass || $this->canSeeClass($user, $filters->schoolClass))
            && (! $filters->classSubject || $this->canSeeClassSubject($user, $filters->classSubject));
    }

    protected function canSeeStudent(User $user, Student $student): bool
    {
        return Student::query()->visibleTo($user)->whereKey($student->id)->exists();
    }

    protected function canSeeClass(User $user, SchoolClass $class): bool
    {
        return $class->isVisibleTo($user);
    }

    protected function canSeeClassSubject(User $user, ClassSubject $classSubject): bool
    {
        return $classSubject->isManagedBy($user);
    }

    /**
     * "18 / 20" with trailing zeros removed.
     */
    protected function score(float|string|null $score, float|string|null $total): string
    {
        return $score === null ? '' : (float) $score.' / '.(float) $total;
    }
}
