<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Records attendance and works out attendance statistics.
 *
 * The attendance percentage is days attended (present or late) over days
 * recorded, leaving excused days out of both: an excused absence neither
 * helps nor hurts.
 */
class AttendanceService
{
    /**
     * Record (or correct) the attendance of several students for one day.
     * Each student keeps one row per class, subject and day.
     *
     * @param  list<array{student_id: int, status: string, remarks?: string|null}>  $records
     * @return int how many rows were written
     */
    public function record(SchoolClass $class, ?ClassSubject $classSubject, CarbonInterface $date, array $records, User $recorder): int
    {
        return DB::transaction(function () use ($class, $classSubject, $date, $records, $recorder) {
            $existing = $this->sheetQuery($class, $classSubject, $date)->lockForUpdate()->get()->keyBy('student_id');

            foreach ($records as $record) {
                $attendance = $existing->get($record['student_id']) ?? new Attendance;
                $attendance->school_class_id = $class->id;
                $attendance->class_subject_id = $classSubject?->id;
                $attendance->student_id = $record['student_id'];
                $attendance->attendance_date = $date->toDateString();
                $attendance->status = AttendanceStatus::from($record['status']);
                $attendance->remarks = $record['remarks'] ?? null;
                $attendance->teacher_id = $recorder->teacher?->id;
                $attendance->recorded_by = $recorder->id;
                $attendance->save();
            }

            return count($records);
        });
    }

    /**
     * The rows of one attendance sheet: a class (and subject) on a day.
     *
     * @return Builder<Attendance>
     */
    public function sheetQuery(SchoolClass $class, ?ClassSubject $classSubject, CarbonInterface $date): Builder
    {
        return Attendance::query()
            ->where('school_class_id', $class->id)
            ->when(
                $classSubject,
                fn (Builder $query) => $query->where('class_subject_id', $classSubject->id),
                fn (Builder $query) => $query->whereNull('class_subject_id'),
            )
            // A range rather than DATE(attendance_date) so the sheet index is used;
            // some databases store the date with a midnight time.
            ->whereBetween('attendance_date', [$date->toDateString(), $date->toDateString().' 23:59:59']);
    }

    /**
     * Counts per status and the attendance percentage, per student, in one
     * aggregate query.
     *
     * @param  Builder<Attendance>  $query  already narrowed to the class, subject, students or dates wanted
     * @return array<int, array{present: int, absent: int, late: int, excused: int, total: int, percentage: float|null}>
     */
    public function summaryByStudent(Builder $query): array
    {
        $rows = $query->toBase()
            ->select('student_id')
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when status = 'present' then 1 else 0 end) as present")
            ->selectRaw("sum(case when status = 'absent' then 1 else 0 end) as absent")
            ->selectRaw("sum(case when status = 'late' then 1 else 0 end) as late")
            ->selectRaw("sum(case when status = 'excused' then 1 else 0 end) as excused")
            ->groupBy('student_id')
            ->get();

        return $rows->mapWithKeys(fn (object $row) => [(int) $row->student_id => self::summarise(
            (int) $row->present,
            (int) $row->absent,
            (int) $row->late,
            (int) $row->excused,
        )])->all();
    }

    /**
     * @return array{present: int, absent: int, late: int, excused: int, total: int, percentage: float|null}
     */
    public static function summarise(int $present, int $absent, int $late, int $excused): array
    {
        $counted = $present + $absent + $late;

        return [
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'excused' => $excused,
            'total' => $counted + $excused,
            'percentage' => $counted > 0 ? round(($present + $late) / $counted * 100, 2) : null,
        ];
    }
}
