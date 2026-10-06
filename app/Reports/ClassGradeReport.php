<?php

namespace App\Reports;

use App\Models\StudentGrade;
use App\Models\User;

/**
 * Every student's current grade in each subject of a class.
 */
class ClassGradeReport extends ScopedReport
{
    public function key(): string
    {
        return 'class-grades';
    }

    public function title(): string
    {
        return 'Class grade report';
    }

    public function description(): string
    {
        return 'Current grades of every student in a class, per subject, with pass and fail counts.';
    }

    public function group(): string
    {
        return 'Classes';
    }

    public function filters(): array
    {
        return ['school_class_id', 'academic_term_id'];
    }

    public function required(): array
    {
        return ['school_class_id'];
    }

    protected function dataPermission(): string
    {
        return 'grades.view';
    }

    public function run(ReportFilters $filters, User $user): ReportResult
    {
        $grades = StudentGrade::query()
            ->current()
            ->whereHas('classSubject', fn ($query) => $query
                ->where('school_class_id', $filters->schoolClass->id)
                ->when($filters->academicTerm, fn ($query, $term) => $query->where('academic_term_id', $term->id))
                // Teachers see the grades of the subjects they teach.
                ->managedBy($user))
            ->with(['student', 'classSubject.subject', 'classSubject.academicTerm'])
            ->get()
            ->sortBy(fn (StudentGrade $grade) => [$grade->student->sortable_name, $grade->classSubject->subject->name])
            ->values();

        return new ReportResult(
            title: $this->title(),
            subtitle: $filters->describe(),
            columns: ['Student', 'Student number', 'Subject', 'Term', 'Grade (%)', 'Descriptor', 'Remarks'],
            rows: $grades->map(fn (StudentGrade $grade) => [
                $grade->student->sortable_name,
                $grade->student->student_number,
                $grade->classSubject->subject->name,
                $grade->classSubject->academicTerm->name,
                (float) $grade->percentage,
                $grade->descriptor,
                $grade->passed ? 'Passed' : 'Failed',
            ])->all(),
            summary: $grades->isEmpty() ? [] : [
                ['label' => 'Class average', 'value' => round($grades->avg(fn (StudentGrade $grade) => (float) $grade->percentage), 2).'%'],
                ['label' => 'Passed / failed', 'value' => $grades->where('passed', true)->count().' / '.$grades->where('passed', false)->count()],
            ],
        );
    }
}
