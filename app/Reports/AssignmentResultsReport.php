<?php

namespace App\Reports;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\ActivitySubmission;
use App\Models\LearningActivity;
use App\Models\Student;
use App\Models\User;

/**
 * Every student's result on every assignment of a class subject.
 */
class AssignmentResultsReport extends ScopedReport
{
    public function key(): string
    {
        return 'assignment-results';
    }

    public function title(): string
    {
        return 'Assignment results';
    }

    public function description(): string
    {
        return 'For one subject: each student\'s status and score on each assignment, and how many handed in.';
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
        return 'submissions.view';
    }

    public function run(ReportFilters $filters, User $user): ReportResult
    {
        $classSubject = $filters->classSubject;

        $activities = LearningActivity::query()
            ->where('class_subject_id', $classSubject->id)
            ->whereIn('type', array_filter(ActivityType::cases(), fn (ActivityType $type) => $type->acceptsSubmissions()))
            ->whereIn('status', [ActivityStatus::Published, ActivityStatus::Closed])
            ->orderBy('due_at')
            ->get();

        $submissions = ActivitySubmission::query()
            ->whereIn('learning_activity_id', $activities->pluck('id'))
            ->get()
            ->keyBy(fn (ActivitySubmission $submission) => "{$submission->learning_activity_id}:{$submission->student_id}");

        $students = Student::query()
            ->whereHas('enrollments', fn ($query) => $query->active()->where('school_class_id', $classSubject->school_class_id))
            ->orderByName()
            ->get();

        $rows = [];
        $handedIn = 0;

        foreach ($activities as $activity) {
            foreach ($students as $student) {
                $submission = $submissions->get("{$activity->id}:{$student->id}");
                $submission?->setRelation('learningActivity', $activity);
                $handedIn += $submission ? 1 : 0;

                $rows[] = [
                    $activity->title,
                    $student->sortable_name,
                    $student->student_number,
                    ucfirst(str_replace('_', ' ', ActivitySubmission::displayStatus($submission, $activity))),
                    $this->score($submission?->score, $activity->total_points),
                ];
            }
        }

        $expected = $activities->count() * $students->count();

        return new ReportResult(
            title: $this->title(),
            subtitle: $filters->describe(),
            columns: ['Activity', 'Student', 'Student number', 'Status', 'Score'],
            rows: $rows,
            summary: [
                ['label' => 'Assignments', 'value' => (string) $activities->count()],
                ['label' => 'Handed in', 'value' => $expected > 0 ? "{$handedIn} of {$expected} (".round($handedIn / $expected * 100).'%)' : '—'],
            ],
        );
    }
}
