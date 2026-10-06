<?php

namespace App\Reports;

use App\Models\Student;
use App\Models\StudentAward;
use App\Models\User;

/**
 * Awards given, optionally for one academic year or class. Revoked awards are left out.
 */
class AwardReport extends ScopedReport
{
    public function key(): string
    {
        return 'awards';
    }

    public function title(): string
    {
        return 'Award report';
    }

    public function description(): string
    {
        return 'Awards given to students, by year or class, with who gave them.';
    }

    public function group(): string
    {
        return 'School';
    }

    public function filters(): array
    {
        return ['academic_year_id', 'school_class_id'];
    }

    protected function dataPermission(): string
    {
        return 'awards.view';
    }

    public function run(ReportFilters $filters, User $user): ReportResult
    {
        $awards = StudentAward::query()
            ->notRevoked()
            ->when($filters->academicYear, fn ($query, $year) => $query->where('academic_year_id', $year->id))
            ->when($filters->schoolClass, fn ($query, $class) => $query->where('school_class_id', $class->id))
            // Teachers only see awards of their own students.
            ->whereIn('student_id', Student::query()->visibleTo($user)->select('id'))
            ->with(['award', 'student', 'schoolClass', 'academicYear', 'awardedBy'])
            ->orderBy('awarded_on')
            ->get();

        return new ReportResult(
            title: $this->title(),
            subtitle: $filters->describe(),
            columns: ['Date', 'Award', 'Student', 'Student number', 'Class', 'Academic year', 'Given by'],
            rows: $awards->map(fn (StudentAward $award) => [
                $award->awarded_on->toDateString(),
                $award->award->name,
                $award->student->sortable_name,
                $award->student->student_number,
                $award->schoolClass?->name,
                $award->academicYear?->name,
                $award->awardedBy?->name,
            ])->all(),
            summary: [
                ['label' => 'Awards given', 'value' => (string) $awards->count()],
                ['label' => 'Students recognised', 'value' => (string) $awards->unique('student_id')->count()],
            ],
        );
    }
}
