<?php

namespace App\Reports;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\AttemptStatus;
use App\Models\ActivityAttempt;
use App\Models\LearningActivity;
use App\Models\Student;
use App\Models\User;

/**
 * Every student's best result on each quiz and examination of a class subject.
 */
class ExaminationResultsReport extends ScopedReport
{
    public function key(): string
    {
        return 'examination-results';
    }

    public function title(): string
    {
        return 'Examination results';
    }

    public function description(): string
    {
        return 'For one subject: each student\'s attempts and best score on each quiz and examination.';
    }

    public function group(): string
    {
        return 'Activities';
    }

    public function filters(): array
    {
        return ['class_subject_id'];
    }

    public function required(): array
    {
        return ['class_subject_id'];
    }

    protected function dataPermission(): string
    {
        return 'examinations.view';
    }

    public function run(ReportFilters $filters, User $user): ReportResult
    {
        $classSubject = $filters->classSubject;

        $activities = LearningActivity::query()
            ->where('class_subject_id', $classSubject->id)
            ->whereIn('type', array_filter(ActivityType::cases(), fn (ActivityType $type) => $type->usesQuestions()))
            ->whereIn('status', [ActivityStatus::Published, ActivityStatus::Closed])
            ->orderBy('due_at')
            ->get();

        $attempts = ActivityAttempt::query()
            ->whereIn('learning_activity_id', $activities->pluck('id'))
            ->get()
            ->groupBy(fn (ActivityAttempt $attempt) => "{$attempt->learning_activity_id}:{$attempt->student_id}");

        $students = Student::query()
            ->whereHas('enrollments', fn ($query) => $query->active()->where('school_class_id', $classSubject->school_class_id))
            ->orderByName()
            ->get();

        $rows = [];
        $summary = [];

        foreach ($activities as $activity) {
            $percentages = [];

            foreach ($students as $student) {
                $mine = $attempts->get("{$activity->id}:{$student->id}", collect());
                $best = $mine->where('status', AttemptStatus::Graded)->sortByDesc(fn (ActivityAttempt $attempt) => (float) $attempt->score)->first();
                $percentage = $best && (float) $activity->total_points > 0 ? round((float) $best->score / (float) $activity->total_points * 100, 2) : null;

                if ($percentage !== null) {
                    $percentages[] = $percentage;
                }

                $rows[] = [
                    $activity->title,
                    $student->sortable_name,
                    $student->student_number,
                    $mine->count(),
                    $this->score($best?->score, $activity->total_points),
                    $percentage,
                ];
            }

            $summary[] = [
                'label' => "{$activity->title} average",
                'value' => $percentages === [] ? '—' : round(array_sum($percentages) / count($percentages), 2).'%',
            ];
        }

        return new ReportResult(
            title: $this->title(),
            subtitle: $filters->describe(),
            columns: ['Activity', 'Student', 'Student number', 'Attempts', 'Best score', 'Percentage'],
            rows: $rows,
            summary: $summary,
        );
    }
}
