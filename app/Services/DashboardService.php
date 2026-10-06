<?php

namespace App\Services;

use App\Enums\ClassStatus;
use App\Enums\StudentStatus;
use App\Enums\SubmissionStatus;
use App\Enums\TeacherStatus;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ActivitySubmission;
use App\Models\ClassSubject;
use App\Models\ClassTeacher;
use App\Models\Enrollment;
use App\Models\LearningActivity;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;

/**
 * Builds the dashboard for each kind of user. Each method returns plain
 * arrays ready for the page; counts are done with aggregate queries rather
 * than by loading records.
 */
class DashboardService
{
    /**
     * Which dashboard this person gets. Decided by what they can do and
     * which profile they have, not by role names.
     */
    public function typeFor(User $user): string
    {
        return match (true) {
            $user->can('classes.create') && $user->can('users.view') => 'admin',
            $user->teacher !== null => 'teacher',
            $user->student !== null => 'student',
            $user->can('teachers.create') => 'hr',
            default => 'basic',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        $type = $this->typeFor($user);

        return [
            'type' => $type,
            'period' => $this->period(),
            ...match ($type) {
                'admin' => $this->admin(),
                'teacher' => $this->teacher($user->teacher),
                'student' => $this->student($user->student),
                'hr' => $this->hr(),
                default => [],
            },
            ...in_array($type, ['teacher', 'student'], true) ? ['upcoming' => $this->upcomingActivities($user)] : [],
        ];
    }

    /**
     * The next published activities this person can see, soonest due first.
     *
     * @return list<array<string, mixed>>
     */
    private function upcomingActivities(User $user): array
    {
        return LearningActivity::query()
            ->visibleTo($user)
            ->upcoming()
            ->with(['classSubject.subject', 'classSubject.schoolClass', 'classSubject.academicTerm'])
            ->limit(5)
            ->get()
            ->map(fn (LearningActivity $activity) => $activity->summary())
            ->all();
    }

    /**
     * The active academic year and term, shown at the top of every dashboard.
     *
     * @return array{year: string|null, term: string|null}
     */
    private function period(): array
    {
        return [
            'year' => AcademicYear::active()?->name,
            'term' => AcademicTerm::active()?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function admin(): array
    {
        $activeYear = AcademicYear::active();

        $classesThisYear = $activeYear
            ? SchoolClass::query()->where('academic_year_id', $activeYear->id)->where('status', ClassStatus::Active)->count()
            : 0;

        $enrolledThisYear = $activeYear
            ? Enrollment::query()->where('academic_year_id', $activeYear->id)->active()->count()
            : 0;

        return [
            'stats' => [
                ['label' => 'Active students', 'value' => Student::where('status', StudentStatus::Active)->count(), 'icon' => 'student', 'href' => route('students.index', ['status' => 'active'], false)],
                ['label' => 'Active teachers', 'value' => Teacher::whereIn('status', [TeacherStatus::Active, TeacherStatus::OnLeave])->count(), 'icon' => 'teacher', 'href' => route('teachers.index', [], false)],
                ['label' => 'Classes this year', 'value' => $classesThisYear, 'icon' => 'class', 'href' => route('classes.index', [], false)],
                ['label' => 'Subjects', 'value' => Subject::count(), 'icon' => 'book', 'href' => route('subjects.index', [], false)],
            ],
            'academic' => [
                ['label' => 'Students enrolled this year', 'value' => $enrolledThisYear],
                ['label' => 'Average class size', 'value' => $classesThisYear > 0 ? round($enrolledThisYear / $classesThisYear, 1) : 0],
                ['label' => 'Classes without an adviser', 'value' => $activeYear ? $this->classesWithoutAdviser($activeYear) : 0],
            ],
            'recent' => $this->recentActivity(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function teacher(Teacher $teacher): array
    {
        $activeYear = AcademicYear::active();

        // Classes the teacher advises or teaches in the active year.
        $classes = SchoolClass::query()
            ->when($activeYear, fn ($query) => $query->where('academic_year_id', $activeYear->id))
            ->where(function ($query) use ($teacher): void {
                $query->whereHas('classTeachers', fn ($query) => $query->where('teacher_id', $teacher->id)->current())
                    ->orWhereHas('classSubjects', fn ($query) => $query->where('teacher_id', $teacher->id));
            })
            ->withCount(['enrollments' => fn ($query) => $query->active()])
            ->orderBy('name')
            ->get();

        return [
            'teacherId' => $teacher->id,
            'classes' => $classes->map(fn (SchoolClass $class) => [
                'id' => $class->id,
                'name' => $class->name,
                'grade_level' => $class->grade_level,
                'students' => $class->enrollments_count,
            ])->all(),
            'stats' => [
                ['label' => 'My classes', 'value' => $classes->count(), 'icon' => 'class'],
                ['label' => 'My students', 'value' => $classes->sum('enrollments_count'), 'icon' => 'student'],
                ['label' => 'Subjects I teach', 'value' => ClassSubject::where('teacher_id', $teacher->id)->count(), 'icon' => 'book'],
                ['label' => 'Work to grade', 'value' => ActivitySubmission::query()
                    ->where('status', SubmissionStatus::Submitted)
                    ->whereHas('learningActivity.classSubject', fn ($query) => $query->where('teacher_id', $teacher->id))
                    ->count(), 'icon' => 'clipboard', 'href' => route('activities.index', [], false)],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function student(Student $student): array
    {
        $enrollments = Enrollment::query()
            ->where('student_id', $student->id)
            ->active()
            ->with('schoolClass')
            ->get();

        return [
            'studentId' => $student->id,
            'classes' => $enrollments->map(fn (Enrollment $enrollment) => [
                'id' => $enrollment->schoolClass->id,
                'name' => $enrollment->schoolClass->name,
                'grade_level' => $enrollment->schoolClass->grade_level,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function hr(): array
    {
        $counts = Teacher::query()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $activeYear = AcademicYear::active();

        // Working teachers with no class or subject in the active year.
        $unassigned = Teacher::query()
            ->whereIn('status', [TeacherStatus::Active, TeacherStatus::OnLeave])
            ->whereDoesntHave('classAssignments', fn ($query) => $query->current())
            ->whereDoesntHave('classSubjects', fn ($query) => $query->when(
                $activeYear,
                fn ($query) => $query->whereHas('academicTerm', fn ($query) => $query->where('academic_year_id', $activeYear->id)),
            ))
            ->orderByName()
            ->limit(10)
            ->get();

        return [
            'stats' => array_map(fn (TeacherStatus $status) => [
                'label' => $status->label().' teachers',
                'value' => (int) ($counts[$status->value] ?? 0),
                'icon' => 'teacher',
                'href' => route('teachers.index', ['status' => $status->value], false),
            ], TeacherStatus::cases()),
            'assignments' => [
                ['label' => 'Current class assignments', 'value' => ClassTeacher::query()->current()->count()],
                ['label' => 'Classes without an adviser', 'value' => $activeYear ? $this->classesWithoutAdviser($activeYear) : 0],
                ['label' => 'Subjects without a teacher', 'value' => ClassSubject::whereNull('teacher_id')->count()],
            ],
            'unassignedTeachers' => $unassigned->map(fn (Teacher $teacher) => [
                'id' => $teacher->id,
                'name' => $teacher->sortable_name,
                'specialization' => $teacher->specialization,
            ])->all(),
        ];
    }

    private function classesWithoutAdviser(AcademicYear $year): int
    {
        return SchoolClass::query()
            ->where('academic_year_id', $year->id)
            ->where('status', ClassStatus::Active)
            ->whereDoesntHave('classTeachers', fn ($query) => $query->current()->where('role', 'adviser'))
            ->count();
    }

    /**
     * The newest students, teachers, classes and enrollments, merged into
     * one list, newest first.
     *
     * @return list<array{label: string, detail: string, when: string, href: string|null}>
     */
    private function recentActivity(): array
    {
        $items = collect();

        Student::query()->latest('id')->limit(5)->get()->each(fn (Student $student) => $items->push([
            'label' => "New student: {$student->full_name}",
            'detail' => $student->student_number,
            'at' => $student->created_at,
            'href' => route('students.show', $student, false),
        ]));

        Teacher::query()->latest('id')->limit(5)->get()->each(fn (Teacher $teacher) => $items->push([
            'label' => "New teacher: {$teacher->full_name}",
            'detail' => $teacher->specialization ?? $teacher->employee_number,
            'at' => $teacher->created_at,
            'href' => route('teachers.show', $teacher, false),
        ]));

        SchoolClass::query()->latest('id')->limit(5)->get()->each(fn (SchoolClass $class) => $items->push([
            'label' => "New class: {$class->name}",
            'detail' => $class->grade_level,
            'at' => $class->created_at,
            'href' => route('classes.show', $class, false),
        ]));

        Enrollment::query()->with(['student', 'schoolClass'])->latest('id')->limit(5)->get()
            ->each(fn (Enrollment $enrollment) => $items->push([
                'label' => "{$enrollment->student->full_name} enrolled",
                'detail' => $enrollment->schoolClass->name,
                'at' => $enrollment->enrolled_at,
                'href' => route('classes.show', $enrollment->school_class_id, false),
            ]));

        return $items
            ->sortByDesc('at')
            ->take(8)
            ->map(fn (array $item) => [
                'label' => $item['label'],
                'detail' => $item['detail'],
                'when' => $item['at']->diffForHumans(),
                'href' => $item['href'],
            ])
            ->values()
            ->all();
    }
}
