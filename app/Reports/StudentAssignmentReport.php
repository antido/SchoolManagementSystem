<?php

namespace App\Reports;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\ActivitySubmission;
use App\Models\LearningActivity;
use App\Models\User;

/**
 * Every assignment given to a student's classes, and what they handed in.
 */
class StudentAssignmentReport extends ScopedReport
{
    public function key(): string
    {
        return 'student-assignments';
    }

    public function title(): string
    {
        return 'Student assignment report';
    }

    public function description(): string
    {
        return 'Assignments and activities for one student: handed in, late, missing or graded, with scores.';
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
        return 'submissions.view';
    }

    public function run(ReportFilters $filters, User $user): ReportResult
    {
        $student = $filters->student;

        $activities = LearningActivity::query()
            ->whereIn('type', array_filter(ActivityType::cases(), fn (ActivityType $type) => $type->acceptsSubmissions()))
            ->whereIn('status', [ActivityStatus::Published, ActivityStatus::Closed])
            ->whereHas('classSubject.schoolClass.enrollments', fn ($query) => $query->where('student_id', $student->id))
            ->when($filters->academicYear, fn ($query, $year) => $query->whereHas('classSubject.schoolClass', fn ($query) => $query->where('academic_year_id', $year->id)))
            ->with('classSubject.subject')
            ->orderBy('due_at')
            ->get();

        $submissions = ActivitySubmission::query()
            ->where('student_id', $student->id)
            ->whereIn('learning_activity_id', $activities->pluck('id'))
            ->get()
            ->keyBy('learning_activity_id');

        $rows = $activities->map(function (LearningActivity $activity) use ($submissions) {
            $submission = $submissions->get($activity->id);
            $submission?->setRelation('learningActivity', $activity);

            return [
                $activity->title,
                $activity->classSubject->subject->name,
                $activity->due_at?->format('Y-m-d H:i'),
                ucfirst(str_replace('_', ' ', ActivitySubmission::displayStatus($submission, $activity))),
                $submission?->submitted_at->format('Y-m-d H:i'),
                $this->score($submission?->score, $activity->total_points),
            ];
        });

        $statuses = $rows->countBy(fn (array $row) => $row[3]);

        return new ReportResult(
            title: $this->title(),
            subtitle: $filters->describe(),
            columns: ['Activity', 'Subject', 'Due', 'Status', 'Handed in', 'Score'],
            rows: $rows->all(),
            summary: [
                ['label' => 'Activities', 'value' => (string) $rows->count()],
                ['label' => 'Missing', 'value' => (string) ($statuses['Missing'] ?? 0)],
                ['label' => 'Late', 'value' => (string) ($statuses['Late'] ?? 0)],
            ],
        );
    }
}
