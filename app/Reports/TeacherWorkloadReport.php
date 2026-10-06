<?php

namespace App\Reports;

use App\Enums\TeacherStatus;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Models\User;

/**
 * How many classes, subjects, students and activities each teacher has.
 */
class TeacherWorkloadReport extends Report
{
    public function key(): string
    {
        return 'teacher-workload';
    }

    public function title(): string
    {
        return 'Teacher workload report';
    }

    public function description(): string
    {
        return 'Per working teacher: classes advised, subjects taught, students and activities, for an academic year.';
    }

    public function group(): string
    {
        return 'School';
    }

    public function filters(): array
    {
        return ['academic_year_id'];
    }

    /**
     * School-wide staffing figures: administrators and HR.
     */
    public function allows(User $user): bool
    {
        return $user->can('reports.view') && $user->can('class_assignments.view') && ! $user->isLimitedToOwnClasses();
    }

    public function allowsFilters(User $user, ReportFilters $filters): bool
    {
        return true;
    }

    public function run(ReportFilters $filters, User $user): ReportResult
    {
        $year = $filters->academicYear ?? AcademicYear::active();
        $inYear = fn ($query) => $year ? $query->whereHas('schoolClass', fn ($query) => $query->where('academic_year_id', $year->id)) : $query;

        $teachers = Teacher::query()
            ->whereIn('status', [TeacherStatus::Active, TeacherStatus::OnLeave])
            ->withCount([
                'classAssignments as advised_count' => fn ($query) => $inYear($query->current()->where('role', 'adviser')),
                'classAssignments as classes_count' => fn ($query) => $inYear($query->current()),
                'classSubjects as subjects_count' => fn ($query) => $inYear($query),
            ])
            ->orderByName()
            ->get();

        // Students and activities per teacher, through the subjects they teach.
        $subjects = ClassSubject::query()
            ->whereIn('teacher_id', $teachers->pluck('id'))
            ->when($year, fn ($query) => $query->whereHas('schoolClass', fn ($query) => $query->where('academic_year_id', $year->id)))
            ->withCount('learningActivities')
            ->get()
            ->groupBy('teacher_id');

        $classSizes = Enrollment::query()
            ->active()
            ->whereIn('school_class_id', $subjects->flatten()->pluck('school_class_id')->unique())
            ->selectRaw('school_class_id, count(*) as total')
            ->groupBy('school_class_id')
            ->pluck('total', 'school_class_id');

        // A class counts once per teacher, however many of its subjects they teach.
        $studentsOf = fn (Teacher $teacher) => (int) $subjects->get($teacher->id, collect())
            ->pluck('school_class_id')->unique()
            ->sum(fn (int $classId) => $classSizes[$classId] ?? 0);

        return new ReportResult(
            title: $this->title(),
            subtitle: [$year ? "Academic year: {$year->name}" : 'All years'],
            columns: ['Teacher', 'Employee number', 'Status', 'Classes advised', 'Classes assigned', 'Subjects taught', 'Students taught', 'Activities'],
            rows: $teachers->map(fn (Teacher $teacher) => [
                $teacher->sortable_name,
                $teacher->employee_number,
                $teacher->status->label(),
                $teacher->advised_count,
                $teacher->classes_count,
                $teacher->subjects_count,
                $studentsOf($teacher),
                (int) $subjects->get($teacher->id, collect())->sum('learning_activities_count'),
            ])->all(),
            summary: [['label' => 'Working teachers', 'value' => (string) $teachers->count()]],
        );
    }
}
