<?php

namespace App\Reports;

use App\Models\User;

/**
 * Every report the app offers.
 */
class ReportRegistry
{
    /**
     * @var list<class-string<Report>>
     */
    private const REPORTS = [
        StudentGradeReport::class,
        StudentAttendanceReport::class,
        StudentAssignmentReport::class,
        StudentExaminationReport::class,
        ClassGradeReport::class,
        ClassAttendanceReport::class,
        AssignmentResultsReport::class,
        ExaminationResultsReport::class,
        AwardReport::class,
        TeacherWorkloadReport::class,
    ];

    /**
     * @return list<Report>
     */
    public function all(): array
    {
        return array_map(fn (string $class) => app($class), self::REPORTS);
    }

    /**
     * The reports this person may open.
     *
     * @return list<Report>
     */
    public function availableTo(User $user): array
    {
        return array_values(array_filter($this->all(), fn (Report $report) => $report->allows($user)));
    }

    public function find(string $key): ?Report
    {
        foreach ($this->all() as $report) {
            if ($report->key() === $key) {
                return $report;
            }
        }

        return null;
    }
}
