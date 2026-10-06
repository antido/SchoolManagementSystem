<?php

namespace App\Notifications;

use App\Models\ClassSubject;
use App\Models\ClassTeacher;

/**
 * To the teacher: they were attached to a class or given a subject to teach.
 */
class TeacherAssignedNotification extends SchoolNotification
{
    /**
     * Load `schoolClass` (and `subject` for a class subject) first.
     */
    public function __construct(private readonly ClassTeacher|ClassSubject $assignment) {}

    protected function title(): string
    {
        $class = $this->assignment->schoolClass->name;

        return $this->assignment instanceof ClassTeacher
            ? "You were assigned to {$class} as {$this->assignment->role->label()}"
            : "You now teach {$this->assignment->subject->name} in {$class}";
    }

    protected function url(): string
    {
        return $this->assignment instanceof ClassTeacher
            ? route('classes.show', $this->assignment->school_class_id, false)
            : route('class-subjects.show', $this->assignment, false);
    }

    protected function icon(): string
    {
        return 'class';
    }
}
