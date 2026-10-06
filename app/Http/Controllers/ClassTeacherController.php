<?php

namespace App\Http\Controllers;

use App\Enums\ClassTeacherRole;
use App\Http\Requests\AssignClassTeacherRequest;
use App\Models\ClassTeacher;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Services\ClassTeacherService;
use Illuminate\Http\RedirectResponse;

/**
 * Teachers are assigned to a class from the class page.
 */
class ClassTeacherController extends Controller
{
    public function __construct(private ClassTeacherService $assignments) {}

    public function store(AssignClassTeacherRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $teacher = Teacher::findOrFail($request->validated('teacher_id'));
        $role = ClassTeacherRole::from($request->validated('role'));

        $this->assignments->assign($schoolClass, $teacher, $role);

        return back()->with('success', "{$teacher->full_name} was assigned to {$schoolClass->name} as {$role->label()}.");
    }

    public function end(ClassTeacher $classTeacher): RedirectResponse
    {
        $this->authorize('end', $classTeacher);

        $this->assignments->end($classTeacher);

        $classTeacher->load('teacher');

        return back()->with('success', "{$classTeacher->teacher->full_name}'s assignment was ended. It stays in the history.");
    }
}
