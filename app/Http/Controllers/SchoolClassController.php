<?php

namespace App\Http\Controllers;

use App\Enums\ActiveStatus;
use App\Enums\ClassStatus;
use App\Enums\ClassTeacherRole;
use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Enums\TeacherStatus;
use App\Http\Requests\SchoolClassRequest;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\ClassTeacher;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolClassController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SchoolClass::class);

        // By default show the active year's classes.
        $defaultYear = AcademicYear::active()?->id;

        $filters = [
            'search' => trim((string) $request->query('search')),
            'academic_year_id' => (string) $request->query('academic_year_id', (string) $defaultYear),
            'status' => (string) $request->query('status'),
        ];

        $classes = SchoolClass::query()
            ->visibleTo($request->user())
            ->with('academicYear')
            ->when($filters['search'] !== '', fn ($query) => $query->search($filters['search']))
            ->when($filters['academic_year_id'] !== '', fn ($query) => $query->where('academic_year_id', $filters['academic_year_id']))
            ->when(ClassStatus::tryFrom($filters['status']), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('grade_level')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SchoolClass $class) => $class->summary());

        return Inertia::render('Classes/Index', [
            'classes' => $classes,
            'filters' => $filters,
            'years' => $this->yearOptions(),
            'statuses' => ClassStatus::options(),
            'can' => ['create' => $request->user()->can('create', SchoolClass::class)],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', SchoolClass::class);

        return Inertia::render('Classes/Create', [
            'years' => $this->yearOptions(),
            'statuses' => ClassStatus::options(),
            'defaultYearId' => AcademicYear::active()?->id,
        ]);
    }

    public function store(SchoolClassRequest $request): RedirectResponse
    {
        $class = new SchoolClass($request->safe()->except('status'));
        $class->status = $request->validated('status');
        $class->save();

        return redirect()
            ->route('classes.show', $class)
            ->with('success', "{$class->name} was created.");
    }

    public function show(Request $request, SchoolClass $schoolClass): Response
    {
        $this->authorize('view', $schoolClass);

        $schoolClass->load('academicYear');
        $user = $request->user();

        return Inertia::render('Classes/Show', [
            'schoolClass' => [
                ...$schoolClass->summary(),
                'description' => $schoolClass->description,
            ],
            'classSubjects' => $schoolClass->classSubjects()
                ->with(['subject', 'schoolClass', 'teacher', 'academicTerm'])
                ->withExists(['learningActivities as has_activities', 'studentGrades as has_grades'])
                ->join('academic_terms', 'academic_terms.id', '=', 'class_subjects.academic_term_id')
                ->orderBy('academic_terms.start_date')
                ->select('class_subjects.*')
                ->get()
                ->map(fn (ClassSubject $classSubject) => [
                    ...$classSubject->summary(),
                    // ClassSubjectPolicy::delete, from facts loaded with the list rather than two queries per subject.
                    'can_delete' => $user->can('subjects.delete') && ! $classSubject->has_activities && ! $classSubject->has_grades,
                ]),
            // Choices for the "Add subject" form.
            'options' => $user->can('create', ClassSubject::class) || $user->can('subjects.update') ? [
                'subjects' => Subject::query()->where('status', ActiveStatus::Active)->orderBy('name')->get()
                    ->map(fn (Subject $subject) => ['value' => $subject->id, 'label' => "{$subject->name} ({$subject->code})"]),
                'terms' => $schoolClass->academicYear->terms
                    ->map(fn (AcademicTerm $term) => ['value' => $term->id, 'label' => $term->name]),
                'teachers' => Teacher::query()
                    ->whereIn('status', [TeacherStatus::Active, TeacherStatus::OnLeave])
                    ->orderByName()
                    ->get()
                    ->map(fn (Teacher $teacher) => ['value' => $teacher->id, 'label' => $teacher->sortable_name]),
            ] : null,
            'roster' => $user->can('viewAny', Enrollment::class) ? $this->roster($schoolClass) : null,
            'teachers' => $this->teachers($schoolClass),
            'teacherOptions' => $user->can('create', ClassTeacher::class) ? [
                'teachers' => Teacher::query()
                    ->whereIn('status', [TeacherStatus::Active, TeacherStatus::OnLeave])
                    ->orderByName()
                    ->get()
                    ->map(fn (Teacher $teacher) => ['value' => $teacher->id, 'label' => $teacher->sortable_name]),
                'roles' => ClassTeacherRole::options(),
            ] : null,
            // Students who could be enrolled. Only loaded when the "Enroll"
            // dialog asks for them, filtered by what was typed in its search box.
            // Only for people who may enroll students: the list covers the whole school.
            'candidates' => Inertia::optional(fn () => $user->can('create', Enrollment::class)
                ? $this->candidates($schoolClass, (string) $request->query('candidate_search'))
                : []),
            'can' => [
                'update' => $user->can('update', $schoolClass),
                'delete' => $user->can('delete', $schoolClass),
                'addSubject' => $user->can('create', ClassSubject::class) && $schoolClass->isActive(),
                'updateSubjects' => $user->can('subjects.update'),
                'enroll' => $user->can('create', Enrollment::class) && $schoolClass->isActive(),
                'withdraw' => $user->can('enrollments.delete'),
                'assignTeacher' => $user->can('create', ClassTeacher::class) && $schoolClass->isActive(),
                'endAssignment' => $user->can('class_assignments.delete'),
            ],
        ]);
    }

    public function edit(SchoolClass $schoolClass): Response
    {
        $this->authorize('update', $schoolClass);

        $schoolClass->load('academicYear');

        return Inertia::render('Classes/Edit', [
            'schoolClass' => [
                ...$schoolClass->summary(),
                'academic_year_id' => $schoolClass->academic_year_id,
                'description' => $schoolClass->description,
            ],
            'years' => $this->yearOptions(),
            'statuses' => ClassStatus::options(),
        ]);
    }

    public function update(SchoolClassRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $schoolClass->fill($request->safe()->except('status'));
        $schoolClass->status = $request->validated('status');
        $schoolClass->save();

        return redirect()
            ->route('classes.show', $schoolClass)
            ->with('success', "{$schoolClass->name} was updated.");
    }

    public function destroy(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        if ($schoolClass->hasDependents() && $request->user()->can('classes.delete')) {
            return back()->with('error', "{$schoolClass->name} has students, teachers or activities. Archive it instead to keep its history.");
        }

        $this->authorize('delete', $schoolClass);

        $schoolClass->delete();

        return redirect()
            ->route('classes.index')
            ->with('success', "{$schoolClass->name} was deleted.");
    }

    /**
     * Current students (alphabetical) and past enrollments (newest first).
     *
     * @return array{current: list<array<string, mixed>>, past: list<array<string, mixed>>}
     */
    private function roster(SchoolClass $class): array
    {
        $enrollments = $class->enrollments()
            ->with('student.user:id,email')
            ->get()
            ->map(fn (Enrollment $enrollment) => [
                'id' => $enrollment->id,
                'status' => $enrollment->status->value,
                'enrolled_at' => $enrollment->enrolled_at->toIso8601String(),
                'ended_at' => $enrollment->ended_at?->toIso8601String(),
                'student' => $enrollment->student->summary(),
            ]);

        return [
            'current' => $enrollments->where('status', EnrollmentStatus::Active->value)
                ->sortBy('student.sortable_name')->values()->all(),
            'past' => $enrollments->where('status', '!=', EnrollmentStatus::Active->value)
                ->sortByDesc('ended_at')->values()->all(),
        ];
    }

    /**
     * The class's advisers and teachers (current and past), and the teachers
     * of its subjects.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function teachers(SchoolClass $class): array
    {
        $assignments = $class->classTeachers()
            ->with('teacher')
            ->orderByDesc('assigned_at')
            ->get()
            ->map(fn (ClassTeacher $assignment) => [
                'id' => $assignment->id,
                'role' => $assignment->role->value,
                'role_label' => $assignment->role->label(),
                'assigned_at' => $assignment->assigned_at->toIso8601String(),
                'ended_at' => $assignment->ended_at?->toIso8601String(),
                'teacher' => ['id' => $assignment->teacher->id, 'name' => $assignment->teacher->full_name],
            ]);

        $subjectTeachers = $class->classSubjects()
            ->whereNotNull('teacher_id')
            ->with(['teacher', 'subject'])
            ->get()
            ->groupBy('teacher_id')
            ->map(fn ($classSubjects) => [
                'teacher' => ['id' => $classSubjects->first()->teacher->id, 'name' => $classSubjects->first()->teacher->full_name],
                'subjects' => $classSubjects->pluck('subject.name')->unique()->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'current' => $assignments->whereNull('ended_at')->values()->all(),
            'past' => $assignments->whereNotNull('ended_at')->values()->all(),
            'subject_teachers' => $subjectTeachers,
        ];
    }

    /**
     * Active students not already in this class, matching the search.
     *
     * @return list<array{id: int, name: string, student_number: string}>
     */
    private function candidates(SchoolClass $class, string $search): array
    {
        return Student::query()
            ->where('status', StudentStatus::Active)
            ->whereDoesntHave('enrollments', fn ($query) => $query->where('school_class_id', $class->id)->active())
            ->when($search !== '', fn ($query) => $query->search($search))
            ->orderByName()
            ->limit(50)
            ->get()
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => $student->sortable_name,
                'student_number' => $student->student_number,
            ])
            ->all();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function yearOptions(): array
    {
        return AcademicYear::query()
            ->latestFirst()
            ->get(['id', 'name', 'is_active'])
            ->map(fn (AcademicYear $year) => [
                'value' => $year->id,
                'label' => $year->name.($year->is_active ? ' (active)' : ''),
            ])
            ->all();
    }
}
