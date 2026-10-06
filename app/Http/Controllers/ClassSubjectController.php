<?php

namespace App\Http\Controllers;

use App\Enums\ActiveStatus;
use App\Enums\ActivityType;
use App\Events\TeacherAssigned;
use App\Http\Requests\ClassSubjectRequest;
use App\Models\ClassSubject;
use App\Models\GradingSystem;
use App\Models\LearningActivity;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\GradeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Subjects taught to a class. They are added and edited from the class page;
 * each one also has its own detail page.
 */
class ClassSubjectController extends Controller
{
    public function __construct(private readonly GradeService $grades) {}

    public function store(ClassSubjectRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $classSubject = new ClassSubject($request->validated());
        $classSubject->schoolClass()->associate($schoolClass);
        $classSubject->status = ActiveStatus::Active;
        $classSubject->save();

        if ($classSubject->teacher_id) {
            TeacherAssigned::dispatch($classSubject);
        }

        $classSubject->load('subject');

        return back()->with('success', "{$classSubject->subject->name} was added to {$schoolClass->name}.");
    }

    public function show(Request $request, ClassSubject $classSubject): Response
    {
        $this->authorize('view', $classSubject);

        $classSubject->load(['subject', 'schoolClass.academicYear', 'teacher', 'academicTerm']);

        return Inertia::render('ClassSubjects/Show', [
            'classSubject' => [
                ...$classSubject->summary(),
                'academic_year' => $classSubject->schoolClass->academicYear->name,
            ],
            'activities' => $classSubject->learningActivities()
                ->visibleTo($request->user())
                ->with(['classSubject.subject', 'classSubject.schoolClass', 'classSubject.academicTerm'])
                ->orderByRaw('due_at is null')
                ->orderByDesc('due_at')
                ->get()
                ->map(fn (LearningActivity $activity) => $activity->summary()),
            ...$this->gradeProps($request, $classSubject),
            'can' => [
                'update' => $request->user()->can('update', $classSubject),
                'createActivity' => $classSubject->isActive()
                    && $classSubject->isManagedBy($request->user())
                    && ActivityType::allowedFor($request->user(), 'create') !== [],
            ],
        ]);
    }

    /**
     * Change the teacher or the status.
     */
    public function update(ClassSubjectRequest $request, ClassSubject $classSubject): RedirectResponse
    {
        $classSubject->update($request->validated());

        // Tell a newly assigned teacher.
        if ($classSubject->wasChanged('teacher_id') && $classSubject->teacher_id) {
            TeacherAssigned::dispatch($classSubject);
        }

        return back()->with('success', 'The class subject was updated.');
    }

    public function destroy(Request $request, ClassSubject $classSubject): RedirectResponse
    {
        if ($classSubject->hasDependents() && $request->user()->can('subjects.delete')) {
            return back()->with('error', 'This class subject has activities or grades. Make it inactive instead.');
        }

        $this->authorize('delete', $classSubject);

        $classSubject->delete();

        return back()->with('success', 'The subject was removed from the class.');
    }

    /**
     * The grade table on the class subject page.
     *
     * @return array<string, mixed>
     */
    private function gradeProps(Request $request, ClassSubject $classSubject): array
    {
        $user = $request->user();

        if (! $user->can('viewGrades', $classSubject)) {
            return [];
        }

        $system = $this->grades->systemFor($classSubject);
        $current = $classSubject->studentGrades()->current()->get()->keyBy('student_id');

        $students = Student::query()
            ->whereHas('enrollments', fn ($query) => $query->active()->where('school_class_id', $classSubject->school_class_id))
            ->orWhereIn('id', $current->keys())
            ->orderByName()
            ->get();

        return [
            'grades' => [
                'system' => $system?->summary(),
                'passing' => $system ? (float) $system->scale->passing_percentage : null,
                'systems' => GradingSystem::query()->orderBy('name')->get()->map(fn (GradingSystem $option) => [
                    'value' => $option->id,
                    'label' => $option->name.($option->is_default ? ' (default)' : ''),
                ]),
                'chosen_system_id' => $classSubject->grading_system_id,
                'rows' => $students->map(fn (Student $student) => [
                    'student' => ['id' => $student->id, 'name' => $student->sortable_name, 'student_number' => $student->student_number],
                    'grade' => $current->get($student->id)?->summary(),
                ])->values(),
                'can_manage' => $user->can('manageGrades', $classSubject),
            ],
        ];
    }
}
