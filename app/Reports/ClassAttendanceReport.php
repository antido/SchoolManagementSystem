<?php

namespace App\Reports;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;

/**
 * Each student's attendance in a class over a period.
 */
class ClassAttendanceReport extends ScopedReport
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function key(): string
    {
        return 'class-attendance';
    }

    public function title(): string
    {
        return 'Class attendance report';
    }

    public function description(): string
    {
        return 'Present, late, absent and excused days for every student in a class, with percentages.';
    }

    public function group(): string
    {
        return 'Classes';
    }

    public function filters(): array
    {
        return ['school_class_id', 'class_subject_id', 'from', 'to'];
    }

    public function required(): array
    {
        return ['school_class_id'];
    }

    protected function dataPermission(): string
    {
        return 'attendance.view';
    }

    public function allowsFilters(User $user, ReportFilters $filters): bool
    {
        // The subject must belong to the class; seeing the class is enough to see its attendance.
        return $this->canSeeClass($user, $filters->schoolClass)
            && (! $filters->classSubject || $filters->classSubject->school_class_id === $filters->schoolClass->id);
    }

    public function run(ReportFilters $filters, User $user): ReportResult
    {
        [$from, $to] = $filters->period();
        $class = $filters->schoolClass;

        $query = Attendance::query()
            ->where('school_class_id', $class->id)
            ->when(
                $filters->classSubject,
                fn ($query, $classSubject) => $query->where('class_subject_id', $classSubject->id),
                fn ($query) => $query->whereNull('class_subject_id'),
            )
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString().' 23:59:59']);

        $summaries = $this->attendance->summaryByStudent($query);

        $students = Student::query()
            ->where(fn ($query) => $query
                ->whereHas('enrollments', fn ($query) => $query->active()->where('school_class_id', $class->id))
                ->orWhereIn('id', array_keys($summaries)))
            ->orderByName()
            ->get();

        $totals = collect($summaries);
        $overall = AttendanceService::summarise($totals->sum('present'), $totals->sum('absent'), $totals->sum('late'), $totals->sum('excused'));

        return new ReportResult(
            title: $this->title(),
            subtitle: [...$filters->describe(), 'Period: '.$from->format('M j, Y').' to '.$to->format('M j, Y'), $filters->classSubject ? 'Subject attendance' : 'Daily attendance'],
            columns: ['Student', 'Student number', 'Present', 'Late', 'Absent', 'Excused', 'Attendance (%)'],
            rows: $students->map(function (Student $student) use ($summaries) {
                $summary = $summaries[$student->id] ?? AttendanceService::summarise(0, 0, 0, 0);

                return [
                    $student->sortable_name,
                    $student->student_number,
                    $summary['present'],
                    $summary['late'],
                    $summary['absent'],
                    $summary['excused'],
                    $summary['percentage'],
                ];
            })->all(),
            summary: [['label' => 'Class attendance', 'value' => $overall['percentage'] === null ? 'No days recorded' : $overall['percentage'].'%']],
        );
    }
}
