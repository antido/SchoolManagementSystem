<?php

namespace App\Services;

use App\Enums\ClassTeacherRole;
use App\Events\TeacherAssigned;
use App\Models\ClassTeacher;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Attaches teachers to classes and ends those assignments, keeping history.
 */
class ClassTeacherService
{
    /**
     * Assign a teacher to a class for the class's academic year.
     *
     * @throws ValidationException when the teacher is already assigned, or
     *                             when an adviser is added to a class that has one.
     */
    public function assign(SchoolClass $class, Teacher $teacher, ClassTeacherRole $role): ClassTeacher
    {
        $assignment = DB::transaction(function () use ($class, $teacher, $role): ClassTeacher {
            $current = ClassTeacher::query()
                ->where('school_class_id', $class->id)
                ->current()
                ->lockForUpdate()
                ->get();

            if ($current->contains('teacher_id', $teacher->id)) {
                throw ValidationException::withMessages([
                    'teacher_id' => "{$teacher->full_name} is already assigned to this class.",
                ]);
            }

            if ($role === ClassTeacherRole::Adviser && $current->contains('role', ClassTeacherRole::Adviser)) {
                throw ValidationException::withMessages([
                    'role' => 'This class already has an adviser. End that assignment first.',
                ]);
            }

            $assignment = new ClassTeacher;
            $assignment->school_class_id = $class->id;
            $assignment->teacher_id = $teacher->id;
            $assignment->academic_year_id = $class->academic_year_id;
            $assignment->role = $role;
            $assignment->assigned_at = now();
            $assignment->save();

            return $assignment;
        });

        TeacherAssigned::dispatch($assignment);

        return $assignment;
    }

    /**
     * End an assignment. The row is kept so the history stays available.
     */
    public function end(ClassTeacher $assignment): void
    {
        $assignment->ended_at = now();
        $assignment->save();
    }
}
