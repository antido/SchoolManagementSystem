<?php

namespace App\Reports;

use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceService;

/**
 * A student's attendance, day by day, over a period.
 */
class StudentAttendanceReport extends ScopedReport
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function key(): string
    {
        return 'student-attendance';
    }

    public function title(): string
    {
        return 'Student attendance report';
    }

    public function description(): string
    {
        return 'Every recorded day for one student, with totals and the attendance percentage.';
    }

    public function group(): string
    {
        return 'Students';
    }

    public function filters(): array
    {
        return ['student_id', 'from', 'to'];
    }

    public function required(): array
    {
        return ['student_id'];
    }

    protected function dataPermission(): string
    {
        return 'attendance.view';
    }

    public function run(ReportFilters $filters, User $user): ReportResult
    {
        [$from, $to] = $filters->period();

        $query = Attendance::query()
            ->where('student_id', $filters->student->id)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString().' 23:59:59']);

        $summary = $this->attendance->summaryByStudent(clone $query)[$filters->student->id] ?? AttendanceService::summarise(0, 0, 0, 0);

        $rows = $query->with(['schoolClass', 'classSubject.subject'])->orderBy('attendance_date')->get()
            ->map(fn (Attendance $attendance) => [
                $attendance->attendance_date->toDateString(),
                $attendance->schoolClass->name,
                $attendance->classSubject?->subject->name ?? 'Daily',
                $attendance->status->label(),
                $attendance->remarks,
            ])->all();

        return new ReportResult(
            title: $this->title(),
            subtitle: [...$filters->describe(), 'Period: '.$from->format('M j, Y').' to '.$to->format('M j, Y')],
            columns: ['Date', 'Class', 'For', 'Status', 'Remarks'],
            rows: $rows,
            summary: [
                ['label' => 'Attendance', 'value' => $summary['percentage'] === null ? 'No days recorded' : $summary['percentage'].'%'],
                ['label' => 'Present / late / absent / excused', 'value' => "{$summary['present']} / {$summary['late']} / {$summary['absent']} / {$summary['excused']}"],
            ],
        );
    }
}
