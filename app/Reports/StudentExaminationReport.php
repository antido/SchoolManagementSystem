<?php

namespace App\Reports;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\AttemptStatus;
use App\Models\ActivityAttempt;
use App\Models\LearningActivity;
use App\Models\User;

/**
 * A student's quizzes, examinations and questionnaires, with their best scores.
 */
class StudentExaminationReport extends ScopedReport
{
    public function key(): string
    {
        return 'student-examinations';
    }

    public function title(): string
    {
        return 'Student examination report';
    }

    public function description(): string
    {
        return 'Quizzes and examinations for one student: attempts, best score and percentage.';
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
        return 'examinations.view';
    }

    public function run(ReportFilters $filters, User $user): ReportResult
    {
        $student = $filters->student;

        $activities = LearningActivity::query()
            ->whereIn('type', array_filter(ActivityType::cases(), fn (ActivityType $type) => $type->usesQuestions()))
            ->whereIn('status', [ActivityStatus::Published, ActivityStatus::Closed])
            ->whereHas('classSubject.schoolClass.enrollments', fn ($query) => $query->where('student_id', $student->id))
            ->when($filters->academicYear, fn ($query, $year) => $query->whereHas('classSubject.schoolClass', fn ($query) => $query->where('academic_year_id', $year->id)))
            ->with('classSubject.subject')
            ->orderBy('due_at')
            ->get();

        $attempts = ActivityAttempt::query()
            ->where('student_id', $student->id)
            ->whereIn('learning_activity_id', $activities->pluck('id'))
            ->get()
            ->groupBy('learning_activity_id');

        $percentages = [];

        $rows = $activities->map(function (LearningActivity $activity) use ($attempts, &$percentages) {
            $mine = $attempts->get($activity->id, collect());
            $best = $mine->where('status', AttemptStatus::Graded)->sortByDesc(fn (ActivityAttempt $attempt) => (float) $attempt->score)->first();
            $percentage = $best && (float) $activity->total_points > 0 ? round((float) $best->score / (float) $activity->total_points * 100, 2) : null;

            if ($percentage !== null) {
                $percentages[] = $percentage;
            }

            return [
                $activity->title,
                $activity->type->label(),
                $activity->classSubject->subject->name,
                $mine->count(),
                $this->score($best?->score, $activity->total_points),
                $percentage,
                match (true) {
                    $best !== null => 'Graded',
                    $mine->isNotEmpty() => 'Waiting for scores',
                    $activity->isPastDue() || $activity->status === ActivityStatus::Closed => 'Missed',
                    default => 'Not taken yet',
                },
            ];
        });

        return new ReportResult(
            title: $this->title(),
            subtitle: $filters->describe(),
            columns: ['Activity', 'Kind', 'Subject', 'Attempts', 'Best score', 'Percentage', 'Status'],
            rows: $rows->all(),
            summary: $percentages === [] ? [] : [
                ['label' => 'Average percentage', 'value' => round(array_sum($percentages) / count($percentages), 2).'%'],
            ],
        );
    }
}
