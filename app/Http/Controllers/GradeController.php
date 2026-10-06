<?php

namespace App\Http\Controllers;

use App\Models\ClassSubject;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Services\GradeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Computed grades: students see their own; teachers and administrators
 * compute and see them for the class subjects they manage.
 */
class GradeController extends Controller
{
    public function __construct(private readonly GradeService $grades) {}

    /**
     * A student's current grades, or the class subjects whose grades the
     * person manages.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('grades.view'), 403);

        if ($user->student !== null) {
            return Inertia::render('Grades/Mine', [
                'grades' => StudentGrade::query()
                    ->where('student_id', $user->student->id)
                    ->current()
                    ->with(['classSubject.subject', 'classSubject.schoolClass', 'classSubject.academicTerm', 'classSubject.teacher'])
                    ->latest('computed_at')
                    ->get()
                    ->map(fn (StudentGrade $grade) => [
                        ...$grade->summary(),
                        'class_subject' => $grade->classSubject->summary(),
                    ]),
                'studentId' => $user->student->id,
            ]);
        }

        return Inertia::render('Grades/Index', [
            'classSubjects' => ClassSubject::query()
                ->managedBy($user)
                ->with(['subject', 'schoolClass', 'academicTerm', 'teacher'])
                ->withCount(['studentGrades as graded_count' => fn ($query) => $query->current()])
                ->withAvg(['studentGrades as average' => fn ($query) => $query->current()], 'percentage')
                ->latest('id')
                ->paginate(20)
                ->through(fn (ClassSubject $classSubject) => [
                    ...$classSubject->summary(),
                    'graded_count' => $classSubject->graded_count,
                    'average' => $classSubject->average === null ? null : round((float) $classSubject->average, 2),
                ]),
        ]);
    }

    /**
     * Compute the grades of everyone in the class subject.
     */
    public function compute(Request $request, ClassSubject $classSubject): RedirectResponse
    {
        $this->authorize('manageGrades', $classSubject);

        try {
            $count = $this->grades->compute($classSubject, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', 'Set up a grading system before computing grades.');
        }

        return back()->with('success', match ($count) {
            0 => 'Grades are up to date.',
            1 => '1 grade was updated.',
            default => "{$count} grades were updated.",
        });
    }

    /**
     * Choose the grading system for the class subject (empty for the default).
     */
    public function chooseSystem(Request $request, ClassSubject $classSubject): RedirectResponse
    {
        $this->authorize('manageGrades', $classSubject);

        $validated = $request->validate([
            'grading_system_id' => ['nullable', 'integer', Rule::exists('grading_systems', 'id')],
        ]);

        $classSubject->forceFill(['grading_system_id' => $validated['grading_system_id'] ?? null])->save();

        return back()->with('success', 'The grading system was changed. Compute grades again to apply it.');
    }

    /**
     * One student's grade in a class subject: the breakdown and its history.
     */
    public function student(Request $request, ClassSubject $classSubject, Student $student): Response
    {
        $user = $request->user();

        // Staff may look at students of this class subject only: someone who is
        // or was in the class, or who has a grade in it.
        $belongs = $student->enrollments()->where('school_class_id', $classSubject->school_class_id)->exists()
            || $classSubject->studentGrades()->where('student_id', $student->id)->exists();

        abort_unless(
            ($user->student?->id === $student->id && $user->can('grades.view')) || ($belongs && $user->can('viewGrades', $classSubject)),
            403,
        );

        $classSubject->load(['subject', 'schoolClass', 'academicTerm', 'teacher']);
        $system = $this->grades->systemFor($classSubject);

        return Inertia::render('Grades/Student', [
            'classSubject' => $classSubject->summary(),
            'student' => ['id' => $student->id, 'full_name' => $student->full_name, 'student_number' => $student->student_number],
            'history' => $classSubject->studentGrades()
                ->where('student_id', $student->id)
                ->latest('computed_at')
                ->latest('id')
                ->get()
                ->map(fn (StudentGrade $grade) => $grade->summary()),
            'system' => $system?->summary(),
            'passing' => $system ? (float) $system->scale->passing_percentage : null,
        ]);
    }
}
