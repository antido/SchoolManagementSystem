<?php

namespace App\Reports;

use App\Models\StudentGrade;
use App\Models\User;

/**
 * A student's current grade in each subject.
 */
class StudentGradeReport extends ScopedReport
{
    public function key(): string
    {
        return 'student-grades';
    }

    public function title(): string
    {
        return 'Student grade report';
    }

    public function description(): string
    {
        return 'A student\'s latest grade in every subject, with descriptor and remarks.';
    }

    public function group(): string
    {
        return 'Students';
    }

    public function filters(): array
    {
        return ['student_id', 'academic_year_id'];
    }

    public function required(): array
    {
        return ['student_id'];
    }

    protected function dataPermission(): string
    {
        return 'grades.view';
    }

    public function run(ReportFilters $filters, User $user): ReportResult
    {
        $grades = StudentGrade::query()
            ->where('student_id', $filters->student->id)
            ->current()
            ->when($filters->academicYear, fn ($query, $year) => $query->whereHas('classSubject.schoolClass', fn ($query) => $query->where('academic_year_id', $year->id)))
            ->with(['classSubject.subject', 'classSubject.schoolClass', 'classSubject.academicTerm'])
            ->get()
            ->sortBy(fn (StudentGrade $grade) => [$grade->classSubject->academicTerm->start_date, $grade->classSubject->subject->name])
            ->values();

        return new ReportResult(
            title: $this->title(),
            subtitle: $filters->describe(),
            columns: ['Subject', 'Class', 'Term', 'Grade (%)', 'Descriptor', 'Remarks'],
            rows: $grades->map(fn (StudentGrade $grade) => [
                $grade->classSubject->subject->name,
                $grade->classSubject->schoolClass->name,
                $grade->classSubject->academicTerm->name,
                (float) $grade->percentage,
                $grade->descriptor,
                $grade->passed ? 'Passed' : 'Failed',
            ])->all(),
            summary: $grades->isEmpty() ? [] : [
                ['label' => 'Average', 'value' => round($grades->avg(fn (StudentGrade $grade) => (float) $grade->percentage), 2).'%'],
                ['label' => 'Subjects passed', 'value' => $grades->where('passed', true)->count().' of '.$grades->count()],
            ],
        );
    }
}
