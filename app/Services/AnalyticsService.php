<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\AttemptStatus;
use App\Models\AcademicYear;
use App\Models\ActivityAttempt;
use App\Models\ActivitySubmission;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\GradingScale;
use App\Models\LearningActivity;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * School performance figures for the analytics page.
 *
 * Everything is limited to the classes the person may see (all of them for
 * administrators, their own for teachers) and is worked out with grouped
 * queries: no figure loads one row per student, answer or day of
 * attendance per student.
 */
class AnalyticsService
{
    /** A student needs attention below this attendance percentage. */
    public const ATTENDANCE_THRESHOLD = 80;

    /** ...or with at least this many missing assignments. */
    public const MISSING_THRESHOLD = 3;

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, ?AcademicYear $year, ?SchoolClass $class): array
    {
        $classIds = SchoolClass::query()
            ->visibleTo($user)
            ->when($year, fn ($query) => $query->where('academic_year_id', $year->id))
            ->when($class, fn ($query) => $query->whereKey($class->id))
            ->pluck('id');

        $scale = GradingScale::default()?->load('bands');
        $passing = $scale ? (float) $scale->passing_percentage : 75.0;

        $classSizes = Enrollment::query()
            ->active()
            ->whereIn('school_class_id', $classIds)
            ->selectRaw('school_class_id, count(*) as total')
            ->groupBy('school_class_id')
            ->pluck('total', 'school_class_id');

        $grades = $this->gradeFigures($classIds, $scale);
        $attendance = $this->attendanceFigures($classIds);
        $completion = $this->completionFigures($classIds, $classSizes);
        $averages = $this->averageGradeByStudent($classIds);

        return [
            'stats' => [
                'students' => Enrollment::query()->active()->whereIn('school_class_id', $classIds)->distinct()->count('student_id'),
                'classes' => $classIds->count(),
                'average_grade' => $grades['average'],
                'attendance_rate' => $attendance['rate'],
                'completion_rate' => $completion['rate'],
                'exam_average' => $this->examAverage($classIds),
            ],
            'passing' => $passing,
            'gradeDistribution' => $grades['distribution'],
            'attendanceTrend' => $attendance['weeks'],
            'enrollmentByClass' => SchoolClass::query()->whereKey($classIds)->orderBy('name')->get(['id', 'name'])
                ->map(fn (SchoolClass $schoolClass) => ['label' => $schoolClass->name, 'value' => (int) ($classSizes[$schoolClass->id] ?? 0)])
                ->sortByDesc('value')
                ->values(),
            'examBySubject' => $this->examBySubject($classIds),
            'topStudents' => $this->topStudents($averages),
            'needsAttention' => $this->needsAttention($classIds, $averages, $passing, $completion['missingByStudent']),
        ];
    }

    /**
     * The average current grade, and how many grades fall in each band of the scale.
     *
     * @param  Collection<int, int>  $classIds
     * @return array{average: float|null, distribution: list<array{label: string, value: int}>}
     */
    private function gradeFigures(Collection $classIds, ?GradingScale $scale): array
    {
        $query = StudentGrade::query()
            ->current()
            ->whereHas('classSubject', fn ($query) => $query->whereIn('school_class_id', $classIds));

        $average = (clone $query)->avg('percentage');

        // Bands from lowest to highest, each counted with one conditional sum.
        $bands = $scale ? $scale->bands->sortBy('min_percentage')->values() : collect();
        $distribution = [];

        if ($bands->isNotEmpty()) {
            $select = $bands->map(function ($band, int $index) use ($bands) {
                $min = (float) $band->min_percentage;
                $next = $bands->get($index + 1);
                $condition = $next ? "percentage >= {$min} and percentage < ".(float) $next->min_percentage : "percentage >= {$min}";

                return "sum(case when {$condition} then 1 else 0 end) as band_{$index}";
            })->implode(', ');

            $counts = (clone $query)->toBase()->selectRaw($select)->first();

            $distribution = $bands->map(fn ($band, int $index) => [
                'label' => $band->label,
                'min' => (float) $band->min_percentage,
                'value' => (int) ($counts->{"band_{$index}"} ?? 0),
            ])->all();
        }

        return [
            'average' => $average === null ? null : round((float) $average, 2),
            'distribution' => $distribution,
        ];
    }

    /**
     * The overall attendance rate and the weekly rate for the last 12 weeks.
     * Days are counted per date in SQL, then grouped into weeks here, which
     * works the same on every database.
     *
     * @param  Collection<int, int>  $classIds
     * @return array{rate: float|null, weeks: list<array{week: string, rate: float|null, days: int}>}
     */
    private function attendanceFigures(Collection $classIds): array
    {
        $from = today()->subWeeks(11)->startOfWeek();

        $perDay = Attendance::query()
            ->whereIn('school_class_id', $classIds)
            ->where('status', '!=', 'excused')
            ->toBase()
            ->selectRaw('attendance_date, count(*) as counted')
            ->selectRaw("sum(case when status in ('present', 'late') then 1 else 0 end) as attended")
            ->groupBy('attendance_date')
            ->get();

        $counted = $perDay->sum('counted');
        $rate = $counted > 0 ? round($perDay->sum('attended') / $counted * 100, 2) : null;

        $byWeek = $perDay
            ->filter(fn (object $day) => Carbon::parse($day->attendance_date)->gte($from))
            ->groupBy(fn (object $day) => Carbon::parse($day->attendance_date)->startOfWeek()->toDateString());

        $weeks = collect(range(0, 11))->map(function (int $offset) use ($from, $byWeek) {
            $week = $from->copy()->addWeeks($offset)->toDateString();
            $days = $byWeek->get($week, collect());
            $counted = $days->sum('counted');

            return [
                'week' => $week,
                'rate' => $counted > 0 ? round($days->sum('attended') / $counted * 100, 1) : null,
                'days' => $days->count(),
            ];
        })->all();

        return ['rate' => $rate, 'weeks' => $weeks];
    }

    /**
     * How much assigned work was handed in, over assignments already due.
     *
     * @param  Collection<int, int>  $classIds
     * @param  Collection<int|string, int|string>  $classSizes
     * @return array{rate: float|null, missingByStudent: Collection<int, int>}
     */
    private function completionFigures(Collection $classIds, Collection $classSizes): array
    {
        $due = LearningActivity::query()
            ->whereIn('learning_activities.type', array_filter(ActivityType::cases(), fn (ActivityType $type) => $type->acceptsSubmissions()))
            ->whereIn('learning_activities.status', [ActivityStatus::Published, ActivityStatus::Closed])
            ->where('learning_activities.due_at', '<', now())
            ->join('class_subjects', 'class_subjects.id', '=', 'learning_activities.class_subject_id')
            ->whereIn('class_subjects.school_class_id', $classIds)
            ->get(['learning_activities.id', 'class_subjects.school_class_id']);

        $expected = $due->sum(fn ($activity) => (int) ($classSizes[$activity->school_class_id] ?? 0));
        $handedIn = ActivitySubmission::query()->whereIn('learning_activity_id', $due->pluck('id'))->count();

        // Missing work per current student: activities due in their class, minus what they handed in.
        $duePerClass = $due->countBy('school_class_id');

        $submittedPerStudent = ActivitySubmission::query()
            ->whereIn('learning_activity_id', $due->pluck('id'))
            ->selectRaw('student_id, count(*) as total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        $missing = Enrollment::query()
            ->active()
            ->whereIn('school_class_id', $duePerClass->keys())
            ->get(['student_id', 'school_class_id'])
            ->groupBy('student_id')
            ->map(fn (Collection $enrollments, int $studentId) => max(0, $enrollments->sum(fn ($enrollment) => $duePerClass[$enrollment->school_class_id] ?? 0) - (int) ($submittedPerStudent[$studentId] ?? 0)));

        return [
            'rate' => $expected > 0 ? round(min($handedIn, $expected) / $expected * 100, 2) : null,
            'missingByStudent' => $missing,
        ];
    }

    /**
     * The average percentage of graded quiz and examination attempts.
     *
     * @param  Collection<int, int>  $classIds
     */
    private function examAverage(Collection $classIds): ?float
    {
        $average = $this->gradedAttempts($classIds)->selectRaw('avg('.$this->percentageExpression().') as average')->value('average');

        return $average === null ? null : round((float) $average, 2);
    }

    /**
     * @param  Collection<int, int>  $classIds
     * @return list<array{label: string, value: float}>
     */
    private function examBySubject(Collection $classIds): array
    {
        return $this->gradedAttempts($classIds)
            ->join('subjects', 'subjects.id', '=', 'class_subjects.subject_id')
            ->selectRaw('subjects.name as label, avg('.$this->percentageExpression().') as value')
            ->groupBy('subjects.name')
            ->orderByDesc('value')
            ->get()
            ->map(fn (object $row) => ['label' => $row->label, 'value' => round((float) $row->value, 1)])
            ->all();
    }

    /**
     * Graded attempts in the classes, joined to their activity and class subject.
     *
     * @param  Collection<int, int>  $classIds
     */
    private function gradedAttempts(Collection $classIds): Builder
    {
        return ActivityAttempt::query()
            ->toBase()
            ->join('learning_activities', 'learning_activities.id', '=', 'activity_attempts.learning_activity_id')
            ->join('class_subjects', 'class_subjects.id', '=', 'learning_activities.class_subject_id')
            ->whereIn('class_subjects.school_class_id', $classIds)
            ->where('activity_attempts.status', AttemptStatus::Graded->value)
            ->where('learning_activities.total_points', '>', 0);
    }

    private function percentageExpression(): string
    {
        return 'activity_attempts.score * 100.0 / learning_activities.total_points';
    }

    /**
     * The ten students with the highest average current grade.
     *
     * @param  Collection<int, float>  $averages  each student's average grade
     * @return list<array<string, mixed>>
     */
    private function topStudents(Collection $averages): array
    {
        $averages = $averages->sortDesc()->take(10);
        $students = Student::query()->whereKey($averages->keys())->get()->keyBy('id');

        return $averages->map(fn (float $average, int $studentId) => [
            'id' => $studentId,
            'name' => $students[$studentId]->sortable_name,
            'average' => round($average, 2),
        ])->values()->all();
    }

    /**
     * Students currently in the classes whose average grade is failing,
     * whose attendance is low, or who have several missing assignments.
     *
     * @param  Collection<int, int>  $classIds
     * @param  Collection<int, float>  $averages  each student's average grade
     * @param  Collection<int, int>  $missingByStudent
     * @return list<array<string, mixed>>
     */
    private function needsAttention(Collection $classIds, Collection $averages, float $passing, Collection $missingByStudent): array
    {
        $attendance = Attendance::query()
            ->whereIn('school_class_id', $classIds)
            ->where('status', '!=', 'excused')
            ->toBase()
            ->selectRaw('student_id, count(*) as counted')
            ->selectRaw("sum(case when status in ('present', 'late') then 1 else 0 end) as attended")
            ->groupBy('student_id')
            ->get()
            ->mapWithKeys(fn (object $row) => [(int) $row->student_id => round($row->attended / $row->counted * 100, 1)]);

        $flagged = collect()
            ->merge($averages->filter(fn (float $average) => $average < $passing)->keys())
            ->merge($attendance->filter(fn (float $rate) => $rate < self::ATTENDANCE_THRESHOLD)->keys())
            ->merge($missingByStudent->filter(fn (int $missing) => $missing >= self::MISSING_THRESHOLD)->keys())
            ->unique();

        $students = Student::query()
            ->whereKey($flagged)
            ->whereHas('enrollments', fn ($query) => $query->active()->whereIn('school_class_id', $classIds))
            ->orderByName()
            ->limit(50)
            ->get();

        return $students->map(fn (Student $student) => [
            'id' => $student->id,
            'name' => $student->sortable_name,
            'average' => isset($averages[$student->id]) ? round($averages[$student->id], 2) : null,
            'attendance' => $attendance[$student->id] ?? null,
            'missing' => (int) ($missingByStudent[$student->id] ?? 0),
            'reasons' => array_values(array_filter([
                isset($averages[$student->id]) && $averages[$student->id] < $passing ? 'Failing average' : null,
                isset($attendance[$student->id]) && $attendance[$student->id] < self::ATTENDANCE_THRESHOLD ? 'Low attendance' : null,
                ($missingByStudent[$student->id] ?? 0) >= self::MISSING_THRESHOLD ? 'Missing work' : null,
            ])),
        ])->all();
    }

    /**
     * Each student's average current grade across the classes' subjects.
     *
     * @param  Collection<int, int>  $classIds
     * @return Collection<int, float>
     */
    private function averageGradeByStudent(Collection $classIds): Collection
    {
        return StudentGrade::query()
            ->current()
            ->whereHas('classSubject', fn ($query) => $query->whereIn('school_class_id', $classIds))
            ->toBase()
            ->selectRaw('student_id, avg(percentage) as average')
            ->groupBy('student_id')
            ->pluck('average', 'student_id')
            ->mapWithKeys(fn ($average, $studentId) => [(int) $studentId => (float) $average]);
    }
}
