<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;

/**
 * Who may record and see attendance.
 *
 * - Daily attendance for a class is recorded by its current advisers and
 *   teachers; subject attendance by the subject's teacher.
 * - Administrators may record any attendance.
 * - Students see only their own.
 */
class AttendancePolicy
{
    public function record(User $user, SchoolClass $class, ?ClassSubject $classSubject = null): bool
    {
        if (! $user->can('attendance.record')) {
            return false;
        }

        if (! $user->isLimitedToOwnClasses()) {
            return true;
        }

        $teacher = $user->teacher;

        if ($teacher === null) {
            return false;
        }

        if ($classSubject !== null) {
            return $classSubject->school_class_id === $class->id && $classSubject->teacher_id === $teacher->id;
        }

        return $class->classTeachers()->current()->where('teacher_id', $teacher->id)->exists();
    }

    /**
     * See the attendance sheets and summary of a whole class.
     */
    public function viewClass(User $user, SchoolClass $class): bool
    {
        return $user->can('attendance.view') && $user->student === null && $class->isVisibleTo($user);
    }

    /**
     * See one student's attendance.
     */
    public function viewStudent(User $user, Student $student): bool
    {
        if ($user->student !== null) {
            return $user->student->id === $student->id && $user->can('attendance.view');
        }

        return $user->can('attendance.view') && Student::query()->visibleTo($user)->whereKey($student->id)->exists();
    }

    /**
     * Policy lookups by model need at least one method for the model itself.
     */
    public function view(User $user, Attendance $attendance): bool
    {
        return $this->viewStudent($user, $attendance->student);
    }
}
